<?php

declare(strict_types=1);

namespace Phil\Transcription\Internal;

use Generator;

/**
 * A `multipart/form-data` body streamed from its fields and one file, under a random boundary: the file is read chunk
 * by chunk as the request is sent.
 *
 * @internal
 */
final readonly class Multipart
{
    public string $boundary;

    /**
     * @param array<string, string> $fields   the fields before the file, by name
     * @param resource              $file
     * @param string                $filename the name the file is sent under, reduced to letters, digits, `.`, `_`
     *                                        and `-`
     */
    public function __construct(
        private array $fields,
        private mixed $file,
        private string $filename,
    ) {
        $this->boundary = 'phil-'.sodium_bin2hex(random_bytes(16));
    }

    public function contentType(): string
    {
        return 'multipart/form-data; boundary='.$this->boundary;
    }

    /**
     * @return Generator<int, string>
     */
    public function body(): Generator
    {
        foreach ($this->fields as $name => $value) {
            yield '--'.$this->boundary."\r\nContent-Disposition: form-data; name=\"".$name."\"\r\n\r\n".$value."\r\n";
        }

        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($this->filename));
        yield '--'.$this->boundary."\r\nContent-Disposition: form-data; name=\"file\"; filename=\"".$filename."\"\r\nContent-Type: application/octet-stream\r\n\r\n";
        yield from Http::chunks($this->file);
        yield "\r\n--".$this->boundary."--\r\n";
    }
}
