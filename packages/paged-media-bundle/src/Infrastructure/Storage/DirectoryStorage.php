<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Infrastructure\Storage;

use Override;
use Phil\PagedMediaBundle\Application\Exception\StorageFailed;
use Phil\PagedMediaBundle\Application\Output;
use Phil\PagedMediaBundle\Application\Port\PdfStorage;

/**
 * Keeps the rendered files in a directory, under their relative paths; a path that leaves the directory is refused.
 */
final readonly class DirectoryStorage implements PdfStorage
{
    public function __construct(private string $directory)
    {
    }

    #[Override]
    public function store(string $path, Output $output): void
    {
        $segments = explode('/', str_replace('\\', '/', $path));

        if ('' === trim($path) || '' === $segments[0] || \in_array('..', $segments, true) || str_contains($path, "\0")) {
            throw StorageFailed::because(\sprintf('A rendered file is kept under a relative path inside the storage; "%s" is not one.', $path));
        }

        $target = $this->directory.'/'.implode('/', $segments);
        $parent = \dirname($target);

        if (!is_dir($parent) && !@mkdir($parent, 0o775, true)) {
            throw StorageFailed::because(\sprintf('The directory "%s" cannot be made.', $parent));
        }

        if (false === @file_put_contents($target, $output->contents)) {
            throw StorageFailed::because(\sprintf('The file "%s" cannot be written.', $target));
        }
    }
}
