<?php

declare(strict_types=1);

namespace Phil\Backup\Encryption;

use Closure;
use Phil\Backup\Exception\BackupFailed;

/**
 * Encrypts and decrypts backup files with libsodium's secret stream (XChaCha20-Poly1305), chunk by chunk, so that a
 * backup of any size passes through a fixed amount of memory.
 *
 * The file is the magic `PHILBAK`, one byte of format version, the stream header, then each chunk as its length on
 * four bytes, big-endian, and its ciphertext. Every chunk is authenticated, with the magic and the version as
 * additional data, and the last one carries the stream's final tag: a file altered, reordered, cut short or extended
 * is refused, as is one encrypted with another key.
 *
 * The target is written as a temporary file of its directory, readable by its owner from the moment it exists, then
 * renamed to the target in one step, replacing a file of that name: it never holds a part of the copy, and a failure
 * leaves it as it was. A missing directory is created with mode 0700; one that is open to its group or to other users
 * is narrowed to 0700 before anything is written in it, or refused when it cannot be narrowed or when
 * `tightenOpenDirectory` is false.
 */
final readonly class BackupCipher
{
    public const string EXTENSION = 'enc';

    private const string MAGIC = 'PHILBAK';

    private const int VERSION = 1;

    private const int CHUNK = 65536;

    public function __construct(
        private BackupKey $key,
        private bool $tightenOpenDirectory = true,
    ) {
    }

    /**
     * Writes the encrypted copy of the source to the target, readable by its owner alone.
     */
    public function encrypt(string $source, string $target): void
    {
        $in = $this->open($source);

        // On a failure, the source closes as the method exits: PHP frees a local resource with its last reference.
        $this->into($target, function ($out, string $file) use ($in): void {
            $pushed = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key->bytes);
            $state = \is_string($pushed[0] ?? null) ? $pushed[0] : '';
            $header = \is_string($pushed[1] ?? null) ? $pushed[1] : '';
            $this->write($out, $file, $this->prefix().$header);
            $chunk = $this->read($in, self::CHUNK);

            do {
                $next = $this->read($in, self::CHUNK);
                $final = '' === $next;
                $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, $this->prefix(), $final ? \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
                $this->write($out, $file, pack('N', \strlen($cipher)).$cipher);
                $chunk = $next;
            } while (!$final);
        });

        fclose($in);
    }

    /**
     * Writes the decrypted copy of the source to the target, readable by its owner alone, once every chunk is
     * authenticated: a file that fails leaves the target as it was.
     */
    public function decrypt(string $source, string $target): void
    {
        $in = $this->open($source);

        $this->into($target, function ($out, string $file) use ($in, $source): void {
            $prefix = $this->read($in, \strlen($this->prefix()));

            if (!str_starts_with($prefix, self::MAGIC)) {
                throw BackupFailed::because(\sprintf('%s is not an encrypted backup.', $source));
            }

            if ($this->prefix() !== $prefix) {
                throw BackupFailed::because(\sprintf('%s is encrypted in a format this version does not read.', $source));
            }

            $header = $this->read($in, \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);

            if (\SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES !== \strlen($header)) {
                throw $this->damaged($source);
            }

            $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($header, $this->key->bytes);

            do {
                // A length cut short reads as a smaller one, whose chunk then fails to authenticate; a length past the
                // largest chunk is refused before anything that large is read.
                $size = (int) hexdec(sodium_bin2hex($this->read($in, 4)));
                $plain = $size > self::CHUNK + \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES ? false : sodium_crypto_secretstream_xchacha20poly1305_pull($state, $this->read($in, $size), $this->prefix());

                if (false === $plain) {
                    throw $this->damaged($source);
                }

                $this->write($out, $file, \is_string($plain[0] ?? null) ? $plain[0] : '');
            } while (\SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL !== ($plain[1] ?? null));

            if (false !== fgetc($in)) {
                throw $this->damaged($source);
            }
        });

        fclose($in);
    }

    /**
     * Gives the writer a stream on a new temporary file of the target's directory, created readable by its owner
     * alone, and the target's path for its messages; once the writer is done and the stream closed, renames the
     * temporary file to the target. The temporary file never outlives a failure.
     *
     * @param Closure(resource, string): void $writer
     */
    private function into(string $target, Closure $writer): void
    {
        $directory = \dirname($target);
        $this->prepare($directory);
        $temporary = $this->temporary($directory, $target);

        try {
            $out = @fopen($temporary, 'w');

            if (false === $out) {
                throw BackupFailed::because(\sprintf('The file %s cannot be written.', $target));
            }

            try {
                $writer($out, $target);
            } finally {
                $closed = fclose($out);
            }

            if (!$closed || !@rename($temporary, $target)) {
                throw BackupFailed::because(\sprintf('The file %s cannot be written.', $target));
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    /**
     * Creates the directory readable by its owner alone, or narrows it to its owner when it is open to others.
     */
    private function prepare(string $directory): void
    {
        if (!is_dir($directory)) {
            @mkdir($directory, 0o700, true);
        }

        // Checked again rather than trusted to mkdir(): another process may have created it at the same moment.
        if (!is_dir($directory)) {
            throw BackupFailed::because(\sprintf('The directory %s cannot be created.', $directory));
        }

        // Windows has no group or other users' bits to narrow.
        if ('Windows' === \PHP_OS_FAMILY) {
            return;
        }

        clearstatcache(true, $directory);
        $mode = fileperms($directory) & 0o777;

        if (0 === ($mode & 0o077)) {
            return;
        }

        if (!$this->tightenOpenDirectory) {
            throw BackupFailed::because(\sprintf('The directory %s is open to other users (mode %04o): narrow it to its owner, with mode 0700.', $directory, $mode));
        }

        if (!@chmod($directory, 0o700)) {
            throw BackupFailed::because(\sprintf('The directory %s is open to other users (mode %04o) and cannot be narrowed to its owner: give it to the user that writes the backups, with mode 0700.', $directory, $mode));
        }
    }

    /**
     * A new empty file of the directory, readable and writable by its owner alone from the moment it exists:
     * tempnam() creates it so. It gives the directory without its symbolic links, and falls back to the system's
     * when it cannot write in it: a file elsewhere is removed and refused.
     */
    private function temporary(string $directory, string $target): string
    {
        $real = (string) realpath($directory);
        $temporary = @tempnam($real, '.phil-backup-');

        if (false === $temporary || \dirname($temporary) !== $real) {
            if (\is_string($temporary)) {
                unlink($temporary);
            }

            throw BackupFailed::because(\sprintf('The file %s cannot be written.', $target));
        }

        return $temporary;
    }

    private function prefix(): string
    {
        return self::MAGIC.\chr(self::VERSION);
    }

    private function damaged(string $file): BackupFailed
    {
        return BackupFailed::because(\sprintf('%s cannot be decrypted: the key is not the one it was encrypted with, or the file is damaged.', $file));
    }

    /**
     * @return resource
     */
    private function open(string $file)
    {
        $stream = @fopen($file, 'r');

        if (false === $stream) {
            throw BackupFailed::because(\sprintf('The file %s cannot be read.', $file));
        }

        return $stream;
    }

    /**
     * Up to the length in bytes, fewer only at the end of the file.
     *
     * @param resource $stream
     */
    private function read($stream, int $length): string
    {
        $data = stream_get_contents($stream, $length);

        return false === $data ? '' : $data;
    }

    /**
     * @param resource $stream
     */
    private function write($stream, string $file, string $data): void
    {
        if (fwrite($stream, $data) !== \strlen($data)) {
            throw BackupFailed::because(\sprintf('The file %s cannot be written.', $file));
        }
    }
}
