<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Infrastructure\MaxMind;

use Phil\Geo\Exception\GeoException;
use Phil\Geo\Geocoding\MaxMind\Database;
use Phil\Geo\Geocoding\MaxMind\Metadata;
use SensitiveParameter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Downloads a MaxMind database with the account of the application and puts it in place of the one it has.
 *
 * The archive is checked against the SHA-256 MaxMind publishes beside it, the database is read out of it as it is
 * decompressed — never whole in memory —, opened to make sure it is the edition asked for, then renamed over the old
 * one in a single step: a lookup reads the old file or the new one, never half of either. The checksum is kept beside
 * the database, and an archive with the same checksum is not downloaded again.
 *
 * One update runs at a time for a database: it holds a lock on `<database>.lock`, left beside the database, and an
 * update that finds the lock taken fails at once. The archive and the extracted file take names of their own in the
 * directory of the database, so that nothing another run wrote is ever read or removed.
 */
final readonly class DatabaseUpdater
{
    public const string BASE_URL = 'https://download.maxmind.com/geoip/databases';

    public const string EDITION = 'GeoLite2-City';

    private const int BLOCK = 512;

    private const int CHUNK = 1_048_576;

    public function __construct(
        private HttpClientInterface $client,
        private string $database,
        private ?string $accountId,
        #[SensitiveParameter]
        private ?string $licenseKey,
        private string $edition = self::EDITION,
        private string $baseUrl = self::BASE_URL,
    ) {
    }

    /**
     * The metadata of the database put in place, or null when the one in place is already the latest.
     *
     * @throws UpdateFailed
     */
    public function update(bool $force = false): ?Metadata
    {
        if (null === $this->accountId || null === $this->licenseKey) {
            throw UpdateFailed::because('set phil_geo.ip_locator.maxmind.account_id and license_key, those of the MaxMind account.');
        }

        $directory = \dirname($this->database);

        if (!is_dir($directory) || !is_writable($directory)) {
            throw UpdateFailed::because(\sprintf('the directory "%s" does not exist or cannot be written.', $directory));
        }

        $lock = is_dir($this->database.'.lock') ? false : fopen($this->database.'.lock', 'c');

        if (false === $lock) {
            throw UpdateFailed::because(\sprintf('the file "%s.lock" cannot be written.', $this->database));
        }

        try {
            if (!flock($lock, \LOCK_EX | \LOCK_NB)) {
                throw UpdateFailed::because('another update of this database is running.');
            }

            return $this->replace([$this->accountId, $this->licenseKey], $force, $directory);
        } finally {
            fclose($lock);
        }
    }

    /**
     * @param array{string, string} $account
     */
    private function replace(array $account, bool $force, string $directory): ?Metadata
    {
        $checksum = $this->checksum($account);
        $kept = is_file($this->database.'.sha256') ? trim((string) file_get_contents($this->database.'.sha256')) : null;

        if (!$force && $checksum === $kept && is_file($this->database)) {
            return null;
        }

        $archive = $this->temporary($directory);

        try {
            $extracted = $this->temporary($directory);
        } catch (UpdateFailed $updateFailed) {
            unlink($archive);

            throw $updateFailed;
        }

        try {
            $this->download($archive, $account);

            if (hash_file('sha256', $archive) !== $checksum) {
                throw UpdateFailed::because('the archive does not match the checksum MaxMind published.');
            }

            $this->extract($archive, $extracted);
            $metadata = $this->check($extracted);
            chmod($extracted, 0o666 & ~umask());
            rename($extracted, $this->database);
            file_put_contents($this->database.'.sha256', $checksum."\n");

            return $metadata;
        } finally {
            foreach ([$archive, $extracted] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * A file of its own beside the database, named after it, that no other run uses.
     */
    private function temporary(string $directory): string
    {
        $file = tempnam($directory, basename($this->database).'.');

        if (false === $file) {
            throw UpdateFailed::because(\sprintf('no file can be written in the directory "%s".', $directory));
        }

        return $file;
    }

    /**
     * @param array{string, string} $account
     */
    private function checksum(array $account): string
    {
        $answer = $this->get('tar.gz.sha256', $account)->getContent();

        if (1 !== preg_match('/^([0-9a-f]{64})\s/', $answer, $matches)) {
            throw UpdateFailed::because('MaxMind answered no checksum.');
        }

        return $matches[1];
    }

    /**
     * Writes the archive as it arrives; the file is closed when the method returns, written or not.
     *
     * @param array{string, string} $account
     */
    private function download(string $archive, array $account): void
    {
        $file = $this->open($archive);

        try {
            foreach ($this->client->stream($this->get('tar.gz', $account)) as $chunk) {
                fwrite($file, $chunk->getContent());
            }
        } catch (ExceptionInterface $exception) {
            throw UpdateFailed::because($exception->getMessage(), $exception);
        }
    }

    /**
     * @param array{string, string} $account
     */
    private function get(string $suffix, array $account): ResponseInterface
    {
        try {
            $response = $this->client->request('GET', rtrim($this->baseUrl, '/').'/'.rawurlencode($this->edition).'/download', [
                'query' => ['suffix' => $suffix],
                'auth_basic' => $account,
            ]);

            if (200 !== $response->getStatusCode()) {
                throw UpdateFailed::because(\sprintf('MaxMind answered HTTP %d for the %s; check the account and the licence key.', $response->getStatusCode(), 'tar.gz' === $suffix ? 'archive' : 'checksum'));
            }

            return $response;
        } catch (ExceptionInterface $exception) {
            throw UpdateFailed::because($exception->getMessage(), $exception);
        }
    }

    /**
     * Copies the database of the edition out of a gzipped tar archive, read block by block, up to the block of zeros
     * that ends the archive.
     */
    private function extract(string $archive, string $target): void
    {
        $tar = gzopen($archive, 'rb');

        if (false === $tar) {
            throw UpdateFailed::because('the archive cannot be opened.');
        }

        while (self::BLOCK === \strlen($header = $this->read($tar, self::BLOCK)) && '' !== trim($header, "\0")) {
            /** @var array{name: string, size: string, type: string} $fields */
            $fields = unpack('a100name/a24ids/a12size/a20times/atype', $header);
            $size = \intval($fields['size'], 8);

            if (\in_array($fields['type'], ['0', "\0"], true) && basename(rtrim($fields['name'], "\0")) === $this->edition.'.mmdb') {
                $this->copy($tar, $size, $target);

                return;
            }

            $this->copy($tar, $size + (self::BLOCK - $size % self::BLOCK) % self::BLOCK, null);
        }

        throw UpdateFailed::because(\sprintf('the archive holds no %s.mmdb file.', $this->edition));
    }

    /**
     * Reads `$size` bytes of the archive into a file, or past them when there is none.
     *
     * @param resource $tar
     */
    private function copy($tar, int $size, ?string $target): void
    {
        $out = null === $target ? null : $this->open($target);

        for ($left = $size; $left > 0; $left -= self::CHUNK) {
            $bytes = $this->read($tar, min(self::CHUNK, $left));

            if (null !== $out) {
                fwrite($out, $bytes);
            }
        }
    }

    /**
     * @param resource $tar
     */
    private function read($tar, int $length): string
    {
        $bytes = gzread($tar, $length);

        return false === $bytes ? '' : $bytes;
    }

    /**
     * @return resource
     */
    private function open(string $path)
    {
        $file = fopen($path, 'w');

        if (false === $file) {
            throw UpdateFailed::because(\sprintf('the file "%s" cannot be written.', $path));
        }

        return $file;
    }

    private function check(string $file): Metadata
    {
        try {
            $metadata = new Database($file)->metadata();
        } catch (GeoException $geoException) {
            throw UpdateFailed::because($geoException->getMessage(), $geoException);
        }

        if ($metadata->databaseType !== $this->edition) {
            throw UpdateFailed::because(\sprintf('the archive holds a %s database, not %s.', $metadata->databaseType, $this->edition));
        }

        return $metadata;
    }
}
