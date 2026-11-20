<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Application\Port;

/**
 * Where the images of templates and designs are read — the photos of blanks, the artworks — and where mockups are
 * written, by paths relative to the store: segments of letters, digits, ".", "_" and "-", never "." nor "..".
 *
 * `Infrastructure\Filesystem\DirectoryImageStore` keeps them in a directory.
 */
interface ImageStore
{
    /**
     * The bytes of the image, or `null` when the store has none at that path.
     */
    public function read(string $path): ?string;

    public function has(string $path): bool;

    /**
     * Writes the image whole, replacing the one at that path; a reader never sees half of it.
     */
    public function write(string $path, string $bytes): void;
}
