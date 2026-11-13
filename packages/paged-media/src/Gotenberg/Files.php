<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

/**
 * What a request works on: files uploaded under `files`, ZIP archives Gotenberg unpacks, each entry counting as a file,
 * and URLs it downloads first.
 */
final readonly class Files
{
    /**
     * @param list<InputFile> $files
     * @param list<InputFile> $archives
     * @param list<Download>  $downloads
     */
    private function __construct(
        public array $files = [],
        public array $archives = [],
        public array $downloads = [],
    ) {
    }

    public static function of(InputFile ...$files): self
    {
        return new self(array_values($files));
    }

    public static function fromPaths(string ...$paths): self
    {
        return new self(array_values(array_map(InputFile::fromPath(...), $paths)));
    }

    public function with(InputFile ...$files): self
    {
        return new self([...$this->files, ...array_values($files)], $this->archives, $this->downloads);
    }

    /**
     * A ZIP archive Gotenberg unpacks; a ZIP sent with `with()` stays a ZIP.
     */
    public function withArchive(InputFile $archive): self
    {
        return new self($this->files, [...$this->archives, $archive], $this->downloads);
    }

    public function withDownload(Download $download): self
    {
        return new self($this->files, $this->archives, [...$this->downloads, $download]);
    }

    public function isEmpty(): bool
    {
        return [] === $this->files && [] === $this->archives && [] === $this->downloads;
    }
}
