<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Application;

use Phil\Fonts\FontFile;
use Phil\Fonts\Format;

/**
 * One font file of the catalogue: its path relative to the directory, with `/` between folders, its format, and what
 * was read of it — the file, or why it could not be read.
 */
final readonly class CatalogueEntry
{
    public function __construct(
        public string $path,
        public string $absolutePath,
        public Format $format,
        public ?FontFile $file = null,
        public ?string $error = null,
    ) {
    }

    public function isReadable(): bool
    {
        return $this->file instanceof FontFile;
    }

    /**
     * The path without its extension: the files of one face in several formats share it.
     */
    public function stem(): string
    {
        $dot = strrpos($this->path, '.');

        return false === $dot ? $this->path : substr($this->path, 0, $dot);
    }
}
