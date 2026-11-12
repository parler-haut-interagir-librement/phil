<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Storage;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Override;
use Phil\MediaBundle\Application\MediaStorage;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;

/**
 * Files on any Flysystem filesystem, by key: a local directory, an S3 bucket, an SFTP server. A key with `..`, or that
 * starts with a slash, is refused before it reaches the filesystem, and a failure of the filesystem is an
 * `InvalidMedia`.
 */
final readonly class FlysystemStorage implements MediaStorage
{
    public function __construct(
        private FilesystemOperator $filesystem,
    ) {
    }

    #[Override]
    public function write(string $key, string $contents): void
    {
        $this->attempt(fn () => $this->filesystem->write($this->checked($key), $contents), $key);
    }

    #[Override]
    public function read(string $key): ?string
    {
        return $this->attempt(fn (): ?string => $this->filesystem->fileExists($this->checked($key)) ? $this->filesystem->read($key) : null, $key);
    }

    #[Override]
    public function deletePrefix(string $prefix): void
    {
        $this->attempt(function () use ($prefix): void {
            if ($this->filesystem->fileExists($this->checked($prefix))) {
                $this->filesystem->delete($prefix);
            } else {
                $this->filesystem->deleteDirectory($prefix);
            }
        }, $prefix);
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    private function attempt(callable $operation, string $key): mixed
    {
        try {
            return $operation();
        } catch (FilesystemException $filesystemException) {
            throw InvalidMedia::because(\sprintf('The storage failed on "%s": %s', $key, $filesystemException->getMessage()), $filesystemException);
        }
    }

    private function checked(string $key): string
    {
        if ('' === $key || str_starts_with($key, '/') || \in_array('..', explode('/', $key), true)) {
            throw InvalidMedia::because(\sprintf('"%s" is not a storage key.', $key));
        }

        return $key;
    }
}
