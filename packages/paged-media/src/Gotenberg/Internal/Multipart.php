<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Internal;

use Generator;
use Phil\PagedMedia\Gotenberg\InputFile;

/**
 * A `multipart/form-data` body written as it is sent: fields, then files read in chunks, so a large file never sits
 * whole in memory.
 *
 * @internal
 */
final class Multipart
{
    /** @var list<array{string, string|InputFile}> */
    private array $parts = [];

    public readonly string $boundary;

    public function __construct(?string $boundary = null)
    {
        $this->boundary = $boundary ?? 'phil-'.hash('xxh128', random_bytes(16));
    }

    public function field(string $name, string $value): void
    {
        $this->parts[] = [$name, $value];
    }

    public function file(string $name, InputFile $file): void
    {
        $this->parts[] = [$name, $file];
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
        foreach ($this->parts as [$name, $value]) {
            if ($value instanceof InputFile) {
                yield \sprintf("--%s\r\nContent-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\nContent-Type: application/octet-stream\r\n\r\n", $this->boundary, $name, $value->name);
                yield from $value->chunks();
            } else {
                yield \sprintf("--%s\r\nContent-Disposition: form-data; name=\"%s\"\r\n\r\n%s", $this->boundary, $name, $value);
            }

            yield "\r\n";
        }

        yield '--'.$this->boundary."--\r\n";
    }
}
