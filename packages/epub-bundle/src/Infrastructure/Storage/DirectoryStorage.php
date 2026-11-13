<?php

declare(strict_types=1);

namespace Phil\EpubBundle\Infrastructure\Storage;

use Override;
use Phil\EpubBundle\Application\Exception\StorageFailed;
use Phil\EpubBundle\Application\Port\EpubStorage;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Keeps the books in a directory, under their relative paths; a path that leaves the directory is refused.
 */
final readonly class DirectoryStorage implements EpubStorage
{
    public function __construct(
        private string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
    }

    #[Override]
    public function store(string $path, string $bytes): string
    {
        $segments = explode('/', str_replace('\\', '/', $path));

        if ('' === trim($path) || '' === $segments[0] || \in_array('..', $segments, true) || str_contains($path, "\0")) {
            throw StorageFailed::because(\sprintf('A book is kept under a relative path inside the storage; "%s" is not one.', $path));
        }

        $target = $this->directory.'/'.implode('/', $segments);

        try {
            $this->filesystem->dumpFile($target, $bytes);
        } catch (IOException $ioException) {
            throw StorageFailed::because(\sprintf('The book "%s" cannot be written: %s', $target, $ioException->getMessage()), $ioException);
        }

        return $target;
    }
}
