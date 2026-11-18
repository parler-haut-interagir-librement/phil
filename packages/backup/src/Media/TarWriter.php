<?php

declare(strict_types=1);

namespace Phil\Backup\Media;

use Phil\Backup\Exception\BackupFailed;

/**
 * Writes a POSIX ustar archive of directories and regular files, written from the format's description: one 512-byte
 * header per entry, the content padded to 512 bytes, two empty blocks at the end. Content is copied stream to stream,
 * so a file of any size passes through a fixed amount of memory.
 *
 * A path longer than 100 bytes is split at a slash into the 155-byte prefix and the name; one that cannot be split,
 * or a file of 8 GiB or more, which the header's octal size cannot hold, is refused.
 *
 * The archive is written as a temporary file beside its path, readable by its owner alone from the moment it exists,
 * and takes its path in one step when it is closed. An archive discarded, or never closed, leaves nothing behind.
 */
final class TarWriter
{
    private const int LARGEST = 0o77777777777;

    private bool $done = false;

    /**
     * @param resource $stream
     */
    private function __construct(
        private $stream,
        private readonly string $file,
        private readonly string $temporary,
    ) {
    }

    public function __destruct()
    {
        $this->discard();
    }

    /**
     * A new archive for the file, readable by its owner alone; an existing file is replaced when it is closed.
     */
    public static function create(string $file): self
    {
        $directory = realpath(\dirname($file));
        $temporary = false === $directory ? false : @tempnam($directory, '.phil-tar-');

        if (false === $temporary || \dirname($temporary) !== $directory) {
            if (\is_string($temporary)) {
                unlink($temporary);
            }

            throw BackupFailed::because(\sprintf('The archive %s cannot be written.', $file));
        }

        // tempnam() creates the file readable and writable by its owner alone; opening it keeps that mode.
        $stream = fopen($temporary, 'w');

        return new self(false === $stream ? throw BackupFailed::because(\sprintf('The archive %s cannot be written.', $file)) : $stream, $file, $temporary);
    }

    public function addDirectory(string $name, int $mode, int $modified): void
    {
        $this->write($this->header(rtrim($name, '/').'/', $mode, 0, $modified, '5'));
    }

    /**
     * Adds the file found at the source under the name.
     */
    public function addFile(string $name, string $source, int $mode, int $modified): void
    {
        $in = @fopen($source, 'r');

        if (false === $in) {
            throw BackupFailed::because(\sprintf('The media file %s cannot be read.', $source));
        }

        // On a failure, the stream closes as the method exits: PHP frees a local resource with its last reference.
        $size = fstat($in)['size'] ?? 0;

        if ($size > self::LARGEST) {
            throw BackupFailed::because(\sprintf('The media file %s is too large for a tar archive: 8 GiB or more.', $source));
        }

        $this->write($this->header($name, $mode, $size, $modified, '0'));

        if (stream_copy_to_stream($in, $this->stream, $size) !== $size) {
            throw BackupFailed::because(\sprintf('The media file %s changed while it was archived.', $source));
        }

        $this->write(str_repeat("\0", (512 - $size % 512) % 512));
        fclose($in);
    }

    /**
     * Ends the archive with its two empty blocks, closes it and gives it its path.
     */
    public function close(): void
    {
        $this->write(str_repeat("\0", 1024));
        fclose($this->stream);
        $this->done = true;

        if (!@rename($this->temporary, $this->file)) {
            unlink($this->temporary);

            throw BackupFailed::because(\sprintf('The archive %s cannot be written.', $this->file));
        }
    }

    /**
     * Gives the archive up: its temporary file is removed and its path left as it was. Nothing happens once it is
     * closed.
     */
    public function discard(): void
    {
        if ($this->done) {
            return;
        }

        $this->done = true;
        fclose($this->stream);
        unlink($this->temporary);
    }

    private function write(string $data): void
    {
        if (fwrite($this->stream, $data) !== \strlen($data)) {
            throw BackupFailed::because(\sprintf('The archive %s cannot be written.', $this->file));
        }
    }

    private function header(string $path, int $mode, int $size, int $modified, string $type): string
    {
        [$prefix, $name] = $this->split($path);

        $header = str_pad($name, 100, "\0")
            .\sprintf('%07o', $mode & 0o777)."\0"
            ."0000000\0"
            ."0000000\0"
            .\sprintf('%011o', $size)."\0"
            .\sprintf('%011o', $modified)."\0"
            .'        '
            .$type
            .str_repeat("\0", 100)
            ."ustar\0".'00'
            .str_repeat("\0", 32 + 32 + 8 + 8)
            .$prefix;
        $header = str_pad($header, 512, "\0");

        return substr_replace($header, \sprintf('%06o', array_sum(array_map(ord(...), str_split($header))))."\0 ", 148, 8);
    }

    /**
     * The ustar prefix and name of a path: the path whole when it fits in 100 bytes, else split at the last slash that
     * leaves both fields short enough.
     *
     * @return array{string, string}
     */
    private function split(string $path): array
    {
        if (\strlen($path) <= 100) {
            return ['', $path];
        }

        if (1 === preg_match('#^(.{1,155})/(.{1,100})$#Ds', $path, $parts)) {
            return [$parts[1], $parts[2]];
        }

        throw BackupFailed::because(\sprintf('The media path %s is too long for a tar archive.', $path));
    }
}
