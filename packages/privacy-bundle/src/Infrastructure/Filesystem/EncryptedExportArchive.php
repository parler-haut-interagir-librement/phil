<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Filesystem;

use DateTimeImmutable;
use Override;
use Phil\PrivacyBundle\Application\Contract\ExportArchive;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Secret\KeyDerivation;
use SensitiveParameter;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Exports as encrypted files of a private directory: `<reference>.<expiry>.export`, `0600` in a `0700` directory, sealed
 * with libsodium under a key derived from the application secret.
 *
 * The expiry is in the name so that a purge needs no index and no decryption. A file that cannot be opened reads as
 * absent: it is not an export anyone can use.
 */
final readonly class EncryptedExportArchive implements ExportArchive
{
    private const string REFERENCE = '/^[0-9a-f]{32}$/D';

    private string $key;

    public function __construct(
        private string $directory,
        #[SensitiveParameter]
        string $secret,
    ) {
        $this->key = KeyDerivation::key($secret, KeyDerivation::EXPORT_ARCHIVE);
    }

    #[Override]
    public function put(string $reference, string $content, DateTimeImmutable $expiresAt): void
    {
        $this->guard($reference);
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->directory, 0o700);

        $nonce = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $path = \sprintf('%s/%s.%d.export', $this->directory, $reference, $expiresAt->getTimestamp());
        $filesystem->dumpFile($path, $nonce.sodium_crypto_secretbox($content, $nonce, $this->key));
        $filesystem->chmod($path, 0o600);
    }

    #[Override]
    public function get(string $reference): ?string
    {
        $this->guard($reference);

        foreach ($this->files($reference.'.*') as $path) {
            $sealed = file_get_contents($path);

            if (false === $sealed || \strlen($sealed) < \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + \SODIUM_CRYPTO_SECRETBOX_MACBYTES) {
                return null;
            }

            $content = sodium_crypto_secretbox_open(substr($sealed, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), substr($sealed, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $this->key);

            return false === $content ? null : $content;
        }

        return null;
    }

    #[Override]
    public function purgeExpired(DateTimeImmutable $now): int
    {
        $purged = 0;

        foreach ($this->files('*') as $path) {
            if ((int) explode('.', basename($path))[1] <= $now->getTimestamp()) {
                new Filesystem()->remove($path);
                ++$purged;
            }
        }

        return $purged;
    }

    /**
     * The exports of the directory; none when it cannot be read, so that an export nobody can read is one that is absent.
     *
     * @return list<string>
     */
    private function files(string $pattern): array
    {
        $files = glob($this->directory.'/'.$pattern.'.export');

        return \is_array($files) ? $files : [];
    }

    private function guard(string $reference): void
    {
        if (1 !== preg_match(self::REFERENCE, $reference)) {
            throw InvalidPrivacyValue::because('An export reference is 32 lowercase hexadecimal characters.');
        }
    }
}
