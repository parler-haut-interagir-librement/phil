<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Infrastructure\Storage;

use Override;
use Phil\CoursesBundle\Application\Contract\ContentStorage;
use Phil\CoursesBundle\Domain\Exception\PackageRefused;
use Phil\ElearningPackages\Exception\PackageRefused as UnsafePath;
use Phil\ElearningPackages\PackagePath;
use Symfony\Component\Filesystem\Filesystem;

/**
 * The files of the imported packages in a directory of the server, outside the application's public directory: the
 * content origin serves them, through the bundle's controller or the web server. Every path is checked again before it
 * is joined to the directory.
 */
final readonly class FilesystemContentStorage implements ContentStorage
{
    private string $directory;

    public function __construct(
        string $directory,
        private Filesystem $filesystem = new Filesystem(),
    ) {
        $this->directory = rtrim($directory, '/');
    }

    #[Override]
    public function write(string $path, string $contents): void
    {
        $this->filesystem->dumpFile($this->path($path), $contents);
    }

    #[Override]
    public function exists(string $path): bool
    {
        return is_file($this->path($path));
    }

    #[Override]
    public function read(string $path): ?string
    {
        $file = $this->path($path);

        if (!is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        return false === $contents ? null : $contents;
    }

    #[Override]
    public function removeDirectory(string $directory): void
    {
        $this->filesystem->remove($this->path($directory));
    }

    /**
     * @throws PackageRefused when the path would leave the directory, or is not one a file of a package has
     */
    private function path(string $path): string
    {
        try {
            return $this->directory.'/'.PackagePath::check($path);
        } catch (UnsafePath $unsafePath) {
            throw PackageRefused::from($unsafePath);
        }
    }
}
