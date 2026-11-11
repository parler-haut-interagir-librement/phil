<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Application\Report;

use InvalidArgumentException;

/**
 * The stream a report is written to or read from: a temporary buffer, a file, the standard output or the body of a
 * response. Whoever opens the stream closes it.
 */
final class ReportStream
{
    /** @var resource */
    private $handle;

    private function __construct(mixed $handle)
    {
        if (!\is_resource($handle) || 'stream' !== get_resource_type($handle)) {
            throw new InvalidArgumentException(\sprintf('A report stream needs an open stream, %s given.', get_debug_type($handle)));
        }

        $this->handle = $handle;
    }

    /**
     * An open stream — what `fopen()` gives, `STDOUT` — to write a report to or read one from.
     *
     * @throws InvalidArgumentException when the handle is not an open stream
     */
    public static function of(mixed $handle): self
    {
        return new self($handle);
    }

    /**
     * An empty buffer, kept in memory up to 2 MB and in a temporary file beyond.
     */
    public static function temporary(): self
    {
        return new self(fopen('php://temp', 'w+'));
    }

    /**
     * Writes the bytes where the stream stands.
     */
    public function write(string $bytes): void
    {
        fwrite($this->handle, $bytes);
    }

    /**
     * Writes one line of a delimited file: fields quoted as RFC 4180 asks, with no escape character, ended by CRLF.
     *
     * @param list<float|int|string|null> $fields
     */
    public function writeFields(array $fields, string $delimiter): void
    {
        fputcsv($this->handle, $fields, $delimiter, '"', '', "\r\n");
    }

    /**
     * Brings the stream back to its start.
     */
    public function rewind(): void
    {
        rewind($this->handle);
    }

    /**
     * What remains of the stream from where it stands, whole.
     */
    public function contents(): string
    {
        return (string) stream_get_contents($this->handle);
    }

    /**
     * Copies what remains of the stream from where it stands to the target, and gives the number of bytes copied.
     */
    public function copyTo(self $target): int
    {
        return (int) stream_copy_to_stream($this->handle, $target->handle);
    }

    public function close(): void
    {
        fclose($this->handle);
    }
}
