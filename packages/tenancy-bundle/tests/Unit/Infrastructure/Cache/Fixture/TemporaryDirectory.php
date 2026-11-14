<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Cache\Fixture;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A directory of its own for the files of a filesystem cache, removed with everything in it.
 */
final readonly class TemporaryDirectory
{
    public string $path;

    public function __construct()
    {
        $this->path = sys_get_temp_dir().'/phil-tenancy-cache-'.bin2hex(random_bytes(6));
    }

    public function remove(): void
    {
        if (!is_dir($this->path)) {
            return;
        }

        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->path);
    }
}
