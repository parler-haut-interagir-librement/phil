<?php

declare(strict_types=1);

namespace Phil\Backup\Media;

use Closure;
use Phil\Backup\Exception\BackupFailed;

/**
 * Reads the ustar archives that `TarWriter` writes: directories and regular files only. Each header's checksum is
 * verified; an archive cut short, with no end block, or holding a link, a device or any other kind of entry, is
 * refused.
 *
 * The end block is two empty 512-byte blocks: one empty block alone, followed by nothing or by anything but a second,
 * is a damaged archive. NUL bytes after the end block, up to the 10 240-byte record that tar pads its archives to,
 * are ignored; anything else after it is refused.
 */
final readonly class TarReader
{
    /**
     * The record of 20 blocks that tar pads an archive to: after the end block, at most a record less those two
     * blocks of padding.
     */
    private const int RECORD = 10240;

    public function __construct(
        private string $file,
    ) {
    }

    /**
     * Calls the visitor with each entry, in order, and with a function that copies a file's content to a path; the
     * content the visitor does not copy is skipped.
     *
     * @param callable(TarEntry, Closure(string): void): void $visitor
     */
    public function read(callable $visitor): void
    {
        $stream = @fopen($this->file, 'r');

        if (false === $stream) {
            throw BackupFailed::because(\sprintf('The archive %s cannot be read.', $this->file));
        }

        // On a failure, the stream closes as the method exits: PHP frees a local resource with its last reference.
        while (($entry = $this->entry($stream)) instanceof TarEntry) {
            $copied = false;
            $visitor($entry, function (string $target) use ($stream, $entry, &$copied): void {
                $copied = true;
                $this->copy($stream, $entry->size, $target);
            });

            if (!$copied) {
                $this->skip($stream, $entry->size);
            }

            $this->skip($stream, (512 - $entry->size % 512) % 512);
        }

        fclose($stream);
    }

    /**
     * The next entry, or null at the end block.
     *
     * @param resource $stream
     */
    private function entry($stream): ?TarEntry
    {
        $header = $this->block($stream);

        if (str_repeat("\0", 512) === $header) {
            $this->end($stream);

            return null;
        }

        // The checksum is the sum of the header's bytes, its own field counted as eight spaces: each byte value times
        // the number of times it occurs, so a header costs one small array instead of one element per byte.
        $checksum = 0;

        foreach (count_chars(substr_replace($header, '        ', 148, 8), 1) as $byte => $count) {
            $checksum += $byte * $count;
        }

        if ("ustar\0" !== substr($header, 257, 6) || $this->number($header, 148, 8) !== $checksum) {
            throw $this->damaged();
        }

        $type = $header[156];
        $name = rtrim(substr($header, 0, 100), "\0");
        $prefix = rtrim(substr($header, 345, 155), "\0");

        if ('0' !== $type && "\0" !== $type && '5' !== $type) {
            throw BackupFailed::because(\sprintf('The archive %s holds %s, which is neither a directory nor a regular file.', $this->file, '' === $prefix ? $name : $prefix.'/'.$name));
        }

        return new TarEntry('' === $prefix ? $name : $prefix.'/'.$name, '5' === $type, $this->number($header, 100, 8), $this->number($header, 124, 12), $this->number($header, 136, 12));
    }

    /**
     * Checks what follows the first empty block: the second, then NUL bytes up to the record at most, then nothing.
     *
     * @param resource $stream
     */
    private function end($stream): void
    {
        $rest = stream_get_contents($stream, self::RECORD - 512);

        if (false === $rest || \strlen($rest) < 512 || '' !== trim($rest, "\0") || false !== fgetc($stream)) {
            throw $this->damaged();
        }
    }

    /**
     * @param resource $stream
     */
    private function block($stream): string
    {
        $block = stream_get_contents($stream, 512);

        if (false === $block || 512 !== \strlen($block)) {
            throw BackupFailed::because(\sprintf('The archive %s is cut short.', $this->file));
        }

        return $block;
    }

    /**
     * An octal field of the header, ended by a NUL or a space.
     */
    private function number(string $header, int $offset, int $length): int
    {
        $field = rtrim(substr($header, $offset, $length), "\0 ");

        if (1 !== preg_match('/^[0-7]+$/D', $field)) {
            throw $this->damaged();
        }

        return (int) octdec($field);
    }

    /**
     * Copies the content to the target; content cut short shows at the next header, which cannot be read.
     *
     * @param resource $stream
     */
    private function copy($stream, int $size, string $target): void
    {
        $out = @fopen($target, 'w');

        if (false === $out) {
            throw BackupFailed::because(\sprintf('The media file %s cannot be written.', $target));
        }

        stream_copy_to_stream($stream, $out, $size);
        fclose($out);
    }

    /**
     * Moves past content left unread; content cut short shows at the next header, which cannot be read.
     *
     * @param resource $stream
     */
    private function skip($stream, int $size): void
    {
        fseek($stream, $size, \SEEK_CUR);
    }

    private function damaged(): BackupFailed
    {
        return BackupFailed::because(\sprintf('The archive %s is damaged.', $this->file));
    }
}
