<?php

declare(strict_types=1);

namespace Phil\BackupBundle\Tests\Unit\Fixture;

/**
 * A directory of its own in the system's temporary directory, removed with everything in it.
 */
final class Files
{
    public static function directory(): string
    {
        $directory = sys_get_temp_dir().'/phil-backup-bundle-'.bin2hex(random_bytes(6));
        mkdir($directory);

        return (string) realpath($directory);
    }

    /**
     * @return list<string>
     */
    public static function glob(string $pattern): array
    {
        $files = glob($pattern);

        return false === $files ? [] : $files;
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
        } elseif (file_exists($path) || is_link($path)) {
            unlink($path);
        }
    }
}
