<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Fixture;

use PHPUnit\Framework\Assert;
use ZipArchive;

/**
 * Opens an archive with the zip extension, the reader the bundle's writer must satisfy, and gives its files.
 */
final class Unzip
{
    private function __construct()
    {
    }

    /**
     * @return array<string, string> the content of each file, by name, in the order of the archive
     */
    public static function files(string $bytes): array
    {
        $path = tempnam(sys_get_temp_dir(), 'phil-privacy-zip');
        Assert::assertIsString($path);
        file_put_contents($path, $bytes);

        try {
            $zip = new ZipArchive();
            Assert::assertTrue($zip->open($path, ZipArchive::CHECKCONS), 'The archive does not pass the consistency check of the zip extension.');
            $files = [];

            for ($index = 0; $index < $zip->numFiles; ++$index) {
                $name = $zip->getNameIndex($index);
                $content = $zip->getFromIndex($index);
                Assert::assertIsString($name);
                Assert::assertIsString($content);
                $files[$name] = $content;
            }

            $zip->close();

            return $files;
        } finally {
            unlink($path);
        }
    }
}
