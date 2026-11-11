<?php

declare(strict_types=1);

namespace Phil\Backup\Encryption;

use Phil\Backup\Exception\BackupFailed;

/**
 * Encrypts and decrypts backup files with libsodium's secret stream (XChaCha20-Poly1305), chunk by chunk, so that a
 * backup of any size passes through a fixed amount of memory.
 *
 * The file is the magic `PHILBAK`, one byte of format version, the stream header, then each chunk as its length on
 * four bytes, big-endian, and its ciphertext. Every chunk is authenticated, with the magic and the version as
 * additional data, and the last one carries the stream's final tag: a file altered, reordered, cut short or extended
 * is refused, as is one encrypted with another key.
 */
final readonly class BackupCipher
{
    public const string EXTENSION = 'enc';

    private const string MAGIC = 'PHILBAK';

    private const int VERSION = 1;

    private const int CHUNK = 65536;

    public function __construct(
        private BackupKey $key,
    ) {
    }

    /**
     * Writes the encrypted copy of the source to the target, readable by its owner alone.
     */
    public function encrypt(string $source, string $target): void
    {
        $in = $this->open($source, 'rb');
        $out = $this->open($target, 'wb');

        // On a failure, the streams close as the method exits: PHP frees a local resource with its last reference.
        chmod($target, 0o600);
        $pushed = sodium_crypto_secretstream_xchacha20poly1305_init_push($this->key->bytes);
        $state = \is_string($pushed[0] ?? null) ? $pushed[0] : '';
        $header = \is_string($pushed[1] ?? null) ? $pushed[1] : '';
        $this->write($out, $target, $this->prefix().$header);
        $chunk = $this->read($in, self::CHUNK);

        do {
            $next = $this->read($in, self::CHUNK);
            $final = '' === $next;
            $cipher = sodium_crypto_secretstream_xchacha20poly1305_push($state, $chunk, $this->prefix(), $final ? \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : \SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
            $this->write($out, $target, pack('N', \strlen($cipher)).$cipher);
            $chunk = $next;
        } while (!$final);

        fclose($in);
        fclose($out);
    }

    /**
     * Writes the decrypted copy of the source to the target, readable by its owner alone, once every chunk is
     * authenticated.
     */
    public function decrypt(string $source, string $target): void
    {
        $in = $this->open($source, 'rb');
        $out = $this->open($target, 'wb');

        chmod($target, 0o600);
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

            $this->write($out, $target, \is_string($plain[0] ?? null) ? $plain[0] : '');
        } while (\SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL !== ($plain[1] ?? null));

        if (false !== fgetc($in)) {
            throw $this->damaged($source);
        }

        fclose($in);
        fclose($out);
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
    private function open(string $file, string $mode)
    {
        $stream = @fopen($file, $mode);

        if (false === $stream) {
            throw BackupFailed::because(\sprintf('The file %s cannot be %s.', $file, 'rb' === $mode ? 'read' : 'written'));
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
