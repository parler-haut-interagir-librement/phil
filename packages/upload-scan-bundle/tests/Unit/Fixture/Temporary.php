<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Fixture;

use Phil\UploadScan\FakeScanner;

/**
 * Files and directories of the tests, under the temporary directory.
 */
final class Temporary
{
    public static function file(string $contents, string $name = 'file.txt'): string
    {
        $directory = self::directory();
        file_put_contents($directory.'/'.$name, $contents);

        return $directory.'/'.$name;
    }

    public static function directory(): string
    {
        $directory = sys_get_temp_dir().'/phil-upload-scan-'.bin2hex(random_bytes(6));
        mkdir($directory, 0o700, true);

        return $directory;
    }

    public static function eicar(): string
    {
        return FakeScanner::eicar();
    }

    public static function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            chmod($path, 0o700);

            $entries = scandir($path);

            foreach (false === $entries ? [] : $entries as $entry) {
                if ('.' !== $entry && '..' !== $entry) {
                    self::remove($path.'/'.$entry);
                }
            }

            rmdir($path);
        } elseif (file_exists($path)) {
            unlink($path);
        }
    }
}
