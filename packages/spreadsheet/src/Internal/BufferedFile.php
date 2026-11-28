<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Internal;

use Phil\Spreadsheet\Exception\WriteFailed;

/**
 * A temporary file written in chunks: the bytes are gathered up to a chunk, then written, so that memory stays that of
 * one chunk whatever the size of the file.
 *
 * @internal
 */
final class BufferedFile
{
    private const int CHUNK = 65536;

    private string $buffer = '';

    private bool $open = true;

    /** @var resource */
    private readonly mixed $file;

    public function __construct(string $path)
    {
        $file = @fopen($path, 'w');

        if (false === $file) {
            throw WriteFailed::because('A temporary file cannot be opened.');
        }

        $this->file = $file;
    }

    public function write(string $bytes): void
    {
        $this->buffer .= $bytes;

        if (\strlen($this->buffer) >= self::CHUNK) {
            $this->flush();
        }
    }

    /**
     * Writes what is left and closes the file.
     */
    public function close(): void
    {
        $this->flush();
        $this->discard();
    }

    /**
     * Closes the file without writing what is left, once: after a failure, or after close().
     */
    public function discard(): void
    {
        if ($this->open) {
            $this->open = false;
            fclose($this->file);
        }
    }

    private function flush(): void
    {
        if (\strlen($this->buffer) !== @fwrite($this->file, $this->buffer)) {
            throw WriteFailed::because('A temporary file refused its bytes.');
        }

        $this->buffer = '';
    }
}
