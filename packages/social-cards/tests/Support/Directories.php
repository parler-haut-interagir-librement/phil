<?php

declare(strict_types=1);

namespace Phil\SocialCards\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Temporary directories of the tests, made and removed.
 */
final class Directories
{
    public static function make(string $name): string
    {
        $directory = sys_get_temp_dir().'/phil-social-cards-'.$name.'-'.bin2hex(random_bytes(4));
        mkdir($directory, 0o777, true);

        return $directory;
    }

    public static function remove(string $directory): void
    {
        /** @var iterable<SplFileInfo> $files */
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
