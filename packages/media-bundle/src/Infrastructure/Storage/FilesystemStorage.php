<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Storage;

use FilesystemIterator;
use Override;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Files in a directory of the server, by key: a key with `..`, or that starts with a slash, is refused.
 */
final readonly class FilesystemStorage implements MediaStorage
{
    public function __construct(
        private string $directory,
    ) {
    }

    #[Override]
    public function write(string $key, string $contents): void
    {
        $path = $this->path($key);

        if (!is_dir(\dirname($path)) && !mkdir(\dirname($path), recursive: true)) {
            throw InvalidMedia::because(\sprintf('The directory of "%s" cannot be created.', $key));
        }

        file_put_contents($path, $contents, \LOCK_EX);
    }

    #[Override]
    public function read(string $key): ?string
    {
        $path = $this->path($key);

        if (!is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return false === $contents ? null : $contents;
    }

    #[Override]
    public function deletePrefix(string $prefix): void
    {
        $path = $this->path($prefix);

        if (is_file($path)) {
            unlink($path);
        } elseif (is_dir($path)) {
            $this->removeDirectory($path);
        }
    }

    private function removeDirectory(string $path): void
    {
        /** @var iterable<SplFileInfo> $entries */
        $entries = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($entries as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($path);
    }

    private function path(string $key): string
    {
        if ('' === $key || str_starts_with($key, '/') || \in_array('..', explode('/', $key), true)) {
            throw InvalidMedia::because(\sprintf('"%s" is not a storage key.', $key));
        }

        return $this->directory.'/'.$key;
    }
}
