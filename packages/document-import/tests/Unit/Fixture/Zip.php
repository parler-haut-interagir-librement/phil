<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Tests\Unit\Fixture;

use RuntimeException;
use ZipArchive;

/**
 * A ZIP archive written for a test from its entries, in order.
 */
final class Zip
{
    /**
     * @param array<string, string> $entries
     */
    public static function of(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'import-test');

        if (false === $path) {
            throw new RuntimeException('No temporary file.');
        }

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::OVERWRITE);

        foreach ($entries as $name => $bytes) {
            $zip->addFromString($name, $bytes);

            if ('mimetype' === $name) {
                $zip->setCompressionName($name, ZipArchive::CM_STORE);
            }
        }

        $zip->close();
        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $bytes;
    }
}
