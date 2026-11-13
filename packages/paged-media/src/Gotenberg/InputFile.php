<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

use Generator;
use Phil\PagedMedia\Gotenberg\Exception\InvalidRequest;
use Phil\PagedMedia\Pdf\Document;

/**
 * A file sent to Gotenberg, read from a path when the request is sent or held in memory. Its name matters: Gotenberg
 * takes the files in the alphanumeric order of their names, names its results after them, and a stage names the files
 * it needs by their names.
 */
final readonly class InputFile
{
    private function __construct(
        public string $name,
        private ?string $path,
        private string $contents,
    ) {
    }

    /**
     * A file on disk, read in chunks when the request is sent; named after it unless a name is given.
     */
    public static function fromPath(string $path, ?string $name = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw InvalidRequest::because(\sprintf('The file "%s" cannot be read.', $path));
        }

        return new self(self::checked($name ?? basename($path)), $path, '');
    }

    public static function fromContents(string $name, string $contents): self
    {
        return new self(self::checked($name), null, $contents);
    }

    /**
     * A document of the native writer, rendered now.
     */
    public static function fromDocument(string $name, Document $document): self
    {
        return self::fromContents($name, $document->render());
    }

    /**
     * The path the file is read from when a request is sent; null for a file held in memory.
     */
    public function path(): ?string
    {
        return $this->path;
    }

    /**
     * The extension of the name, in lower case, without the dot; empty when the name has none.
     */
    public function extension(): string
    {
        return strtolower(pathinfo($this->name, \PATHINFO_EXTENSION));
    }

    /**
     * Whether both are the same file: the same name and the same path, or the same name and the same bytes.
     */
    public function sameAs(self $other): bool
    {
        return $this->name === $other->name && $this->path === $other->path && $this->contents === $other->contents;
    }

    /**
     * The file's bytes, in chunks of at most the given size.
     *
     * @return Generator<int, string>
     */
    public function chunks(int $size = 1_048_576): Generator
    {
        if (null === $this->path) {
            yield $this->contents;

            return;
        }

        $handle = @fopen($this->path, 'r');

        if (false === $handle) {
            throw InvalidRequest::because(\sprintf('The file "%s" cannot be read.', $this->path));
        }

        try {
            while (\is_string($chunk = fread($handle, max(1, $size))) && '' !== $chunk) {
                yield $chunk;
            }
        } finally {
            fclose($handle);
        }
    }

    private static function checked(string $name): string
    {
        if ('' === $name || false !== strpbrk($name, "/\\\"\r\n\0")) {
            throw InvalidRequest::because(\sprintf('A file name is not empty and holds no slash, backslash, quote or line break; "%s" does.', $name));
        }

        return $name;
    }
}
