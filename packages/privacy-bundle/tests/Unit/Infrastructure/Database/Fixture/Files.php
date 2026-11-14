<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Database\Fixture;

/**
 * A directory of its own in the system's temporary directory, removed with everything in it.
 */
final class Files
{
    public static function directory(): string
    {
        $directory = sys_get_temp_dir().'/phil-privacy-'.bin2hex(random_bytes(6));
        mkdir($directory);

        return $directory;
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
        if (is_dir($path)) {
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
