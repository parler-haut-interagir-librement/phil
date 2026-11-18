<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Infrastructure\Quarantine;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DirectoryIterator;
use Override;
use Phil\UploadScan\ScanResult;
use Phil\UploadScanBundle\Application\Contract\Quarantine;
use Phil\UploadScanBundle\Application\Contract\QuarantineInventory;
use Phil\UploadScanBundle\Application\Exception\QuarantineFailed;
use Phil\UploadScanBundle\Application\QuarantinedFile;
use Psr\Clock\ClockInterface;
use SplFileInfo;
use UnexpectedValueException;

/**
 * The quarantine in a local directory, created readable by its owner only: each file kept as `<id>.quarantine`, read
 * and written by its owner only, beside `<id>.json`, which holds the name it was sent under, the signature found and
 * when it was kept. The identifier is 32 random hexadecimal digits; nothing the sender chose ever becomes a path.
 *
 * A directory that already exists and is open to its group or to other users is narrowed to 0700 before a file is
 * kept in it, or refused when it cannot be narrowed or when `tightenOpenDirectory` is false. Both files are written
 * as temporary files of the directory, readable by their owner from the moment they exist, then given their names
 * in one step, the record last; a failure leaves neither behind.
 *
 * Its inventory lists the records, the most recently kept first by the time of their file, reads one by its
 * identifier — anything else than 32 hexadecimal digits names nothing — and deletes a file with its record. A record
 * that cannot be read is still listed, under an empty name and the time of its file, so that it can be deleted.
 */
final readonly class FilesystemQuarantine implements Quarantine, QuarantineInventory
{
    /** The longest name kept in the record, in bytes. */
    public const int NAME_BYTES = 255;

    private const string ID = '/^[0-9a-f]{32}$/D';

    private const string RECORD = '/^([0-9a-f]{32})\.json$/D';

    public function __construct(
        private string $directory,
        private ?ClockInterface $clock = null,
        private bool $tightenOpenDirectory = true,
    ) {
    }

    #[Override]
    public function isolate(mixed $stream, string $name, ScanResult $result): string
    {
        $this->prepare();
        $id = sodium_bin2hex(random_bytes(16));
        $content = $this->temporary();
        $record = null;

        try {
            $target = $this->quietly(static fn (): mixed => fopen($content, 'w'));

            if (!\is_resource($target)) {
                throw QuarantineFailed::because(\sprintf('The quarantine directory %s does not accept files.', $this->directory));
            }

            try {
                $copied = \is_resource($stream) && false !== $this->quietly(static fn (): int|false => stream_copy_to_stream($stream, $target));
            } finally {
                fclose($target);
            }

            $record = $this->temporary();
            $json = json_encode([
                'name' => substr((string) preg_replace('/[\x00-\x1F\x7F]/', '', $name), 0, self::NAME_BYTES),
                'signature' => $result->signature,
                'quarantined_at' => ($this->clock?->now() ?? new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR);

            if (!$copied || false === $this->quietly(static fn (): int|false => file_put_contents($record, $json."\n"))) {
                throw QuarantineFailed::because(\sprintf('The file %s could not be written whole to the quarantine.', $id));
            }

            if (!$this->publish($content, $id.'.quarantine') || !$this->publish($record, $id.'.json')) {
                $this->quietly(fn (): bool => unlink($this->directory.'/'.$id.'.quarantine'));
                throw QuarantineFailed::because(\sprintf('The file %s could not be written whole to the quarantine.', $id));
            }
        } finally {
            foreach ([$content, $record] as $temporary) {
                if (null !== $temporary && is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }

        return $id;
    }

    #[Override]
    public function files(int $limit, int $offset = 0): array
    {
        $times = [];

        try {
            foreach (new DirectoryIterator($this->directory) as $entry) {
                if (1 === preg_match(self::RECORD, $entry->getFilename(), $match)) {
                    $times[$match[1]] = $entry->getMTime();
                }
            }
        } catch (UnexpectedValueException) {
            // The quarantine is not created yet: it holds nothing.
        }

        uksort($times, static fn (string $a, string $b): int => [$times[$b], $a] <=> [$times[$a], $b]);
        $files = [];

        foreach (\array_slice(array_keys($times), $offset, $limit) as $id) {
            $file = $this->file($id);

            if ($file instanceof QuarantinedFile) {
                $files[] = $file;
            }
        }

        return $files;
    }

    #[Override]
    public function file(string $id): ?QuarantinedFile
    {
        $path = $this->directory.'/'.$id.'.json';

        if (1 !== preg_match(self::ID, $id) || !is_file($path)) {
            return null;
        }

        $record = json_decode((string) $this->quietly(static fn (): string|false => file_get_contents($path)), true);
        $record = \is_array($record) ? $record : [];

        $at = \is_string($record['quarantined_at'] ?? null) ? DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $record['quarantined_at']) : false;
        $size = $this->quietly(fn (): int|false => filesize($this->directory.'/'.$id.'.quarantine'));

        return new QuarantinedFile(
            $id,
            \is_string($record['name'] ?? null) ? $record['name'] : '',
            \is_string($record['signature'] ?? null) ? $record['signature'] : null,
            false === $at ? new DateTimeImmutable('@'.new SplFileInfo($path)->getMTime()) : $at,
            false === $size ? 0 : $size,
        );
    }

    #[Override]
    public function delete(string $id): bool
    {
        if (1 !== preg_match(self::ID, $id)) {
            return false;
        }

        $deleted = false;

        foreach (['.quarantine', '.json'] as $extension) {
            $path = $this->directory.'/'.$id.$extension;
            $deleted = $this->quietly(static fn (): bool => unlink($path)) || $deleted;
        }

        return $deleted;
    }

    /**
     * Creates the directory readable by its owner only, or narrows it to its owner when it is open to others.
     */
    private function prepare(): void
    {
        if (!is_dir($this->directory) && !$this->quietly(fn (): bool => mkdir($this->directory, 0o700, true))) {
            throw QuarantineFailed::because(\sprintf('The quarantine directory %s cannot be created.', $this->directory));
        }

        // Windows has no group or other users' bits to narrow.
        if ('Windows' === \PHP_OS_FAMILY) {
            return;
        }

        clearstatcache(true, $this->directory);
        $mode = fileperms($this->directory) & 0o777;

        if (0 === ($mode & 0o077)) {
            return;
        }

        if (!$this->tightenOpenDirectory) {
            throw QuarantineFailed::because(\sprintf('The quarantine directory %s is open to other users (mode %04o): narrow it to its owner, with mode 0700.', $this->directory, $mode));
        }

        if (!$this->quietly(fn (): bool => chmod($this->directory, 0o700))) {
            throw QuarantineFailed::because(\sprintf('The quarantine directory %s is open to other users (mode %04o) and cannot be narrowed to its owner: give it to the user that runs the application, with mode 0700.', $this->directory, $mode));
        }
    }

    /**
     * A new empty file of the directory, readable and writable by its owner only from the moment it exists:
     * tempnam() creates it so. It gives the directory without its symbolic links, and falls back to the system's
     * when it cannot write in it: a file elsewhere is removed and refused.
     */
    private function temporary(): string
    {
        $directory = (string) realpath($this->directory);
        $file = $this->quietly(static fn (): string|false => tempnam($directory, '.phil-quarantine-'));

        if (false === $file || \dirname($file) !== $directory) {
            if (\is_string($file)) {
                unlink($file);
            }

            throw QuarantineFailed::because(\sprintf('The quarantine directory %s does not accept files.', $this->directory));
        }

        return $file;
    }

    /**
     * Gives a temporary file its name in the directory, in one step.
     */
    private function publish(string $temporary, string $name): bool
    {
        return $this->quietly(fn (): bool => rename($temporary, $this->directory.'/'.$name));
    }

    /**
     * @template T
     *
     * @param Closure(): T $io
     *
     * @return T
     */
    private function quietly(Closure $io): mixed
    {
        set_error_handler(static fn (): bool => true);

        try {
            return $io();
        } finally {
            restore_error_handler();
        }
    }
}
