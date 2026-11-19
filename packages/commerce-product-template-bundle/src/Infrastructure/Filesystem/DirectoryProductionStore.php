<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Infrastructure\Filesystem;

use Override;
use Phil\CommerceProductTemplateBundle\Application\Port\ProductionStore;

/**
 * `ProductionStore` in a directory readable by its owner only: directories in mode 0700, files in mode 0600, since
 * print files and production orders hold what buyers typed. Paths are checked, and files written whole, as
 * `DirectoryImageStore` does.
 */
final readonly class DirectoryProductionStore implements ProductionStore
{
    private DirectoryImageStore $files;

    public function __construct(string $directory)
    {
        $this->files = new DirectoryImageStore($directory, 0o700, 0o600);
    }

    #[Override]
    public function read(string $path): ?string
    {
        return $this->files->read($path);
    }

    #[Override]
    public function has(string $path): bool
    {
        return $this->files->has($path);
    }

    #[Override]
    public function write(string $path, string $bytes): void
    {
        $this->files->write($path, $bytes);
    }
}
