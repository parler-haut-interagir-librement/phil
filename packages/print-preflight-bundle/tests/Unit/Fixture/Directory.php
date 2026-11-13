<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Unit\Fixture;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Temporary directories of files for the tests, removed by `clean()`.
 */
final class Directory
{
    /** @var list<string> */
    private static array $made = [];

    /**
     * @param array<string, string> $files contents by path relative to the directory
     */
    public static function with(array $files): string
    {
        $directory = sys_get_temp_dir().'/phil-print-preflight-bundle-test/'.bin2hex(random_bytes(6));
        self::$made[] = $directory;
        $filesystem = new Filesystem();
        $filesystem->mkdir($directory);

        foreach ($files as $path => $contents) {
            $filesystem->dumpFile($directory.'/'.$path, $contents);
        }

        return $directory;
    }

    public static function clean(): void
    {
        new Filesystem()->remove(self::$made);
        self::$made = [];
    }
}
