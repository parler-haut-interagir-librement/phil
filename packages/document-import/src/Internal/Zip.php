<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal;

use Closure;
use Phil\DocumentImport\Exception\ExportFailed;
use Phil\DocumentImport\Exception\InvalidDocument;
use ZipArchive;

/**
 * A ZIP archive opened from bytes, or written to bytes, in memory with `ZipArchive::openString()` and
 * `ZipArchive::closeString()` where the PHP version has them, through a temporary file removed at once otherwise.
 *
 * @internal
 */
final class Zip
{
    /**
     * Whether this PHP opens and writes an archive in memory.
     */
    public static function inMemory(): bool
    {
        return method_exists(ZipArchive::class, 'openString');
    }

    /**
     * What `$read` takes from the archive of the bytes given, in memory when `$inMemory` asks for it and this PHP
     * allows it.
     *
     * @template T
     *
     * @param Closure(ZipArchive): T $read
     *
     * @return T
     *
     * @throws InvalidDocument when the bytes are not a ZIP archive, or no temporary file can be made
     */
    public static function read(string $bytes, Closure $read, bool $inMemory, ?string $directory = null): mixed
    {
        $zip = new ZipArchive();

        if ($inMemory && self::inMemory()) {
            if (!self::openString($zip, $bytes)) {
                throw InvalidDocument::because('The file is not a ZIP archive.');
            }

            try {
                return $read($zip);
            } finally {
                $zip->close();
            }
        }

        $path = tempnam($directory ?? sys_get_temp_dir(), 'phil-import');

        if (false === $path) {
            throw InvalidDocument::because('No temporary file can be made to open the archive.');
        }

        try {
            file_put_contents($path, $bytes);

            if (true !== $zip->open($path)) {
                throw InvalidDocument::because('The file is not a ZIP archive.');
            }

            try {
                return $read($zip);
            } finally {
                $zip->close();
            }
        } finally {
            unlink($path);
        }
    }

    /**
     * The bytes of the archive `$write` fills, in memory when `$inMemory` asks for it and this PHP allows it.
     *
     * @param Closure(ZipArchive): void $write
     *
     * @throws ExportFailed when the archive cannot be created or written
     */
    public static function write(Closure $write, bool $inMemory, ?string $directory = null): string
    {
        $zip = new ZipArchive();

        if ($inMemory && self::inMemory()) {
            self::openString($zip, '');
            $write($zip);
            $bytes = self::closeString($zip);

            if ('' === $bytes) {
                throw ExportFailed::because('The archive cannot be written.');
            }

            return $bytes;
        }

        $path = tempnam($directory ?? sys_get_temp_dir(), 'phil-export');

        if (false === $path) {
            throw ExportFailed::because('No temporary file can be created.');
        }

        try {
            if (true !== $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
                throw ExportFailed::because('The archive cannot be created.');
            }

            $write($zip);

            if (!$zip->close()) {
                throw ExportFailed::because('The archive cannot be written.');
            }

            return (string) file_get_contents($path);
        } finally {
            unlink($path);
        }
    }

    /**
     * Whether the archive opens from the bytes, kept in memory; the PHP versions without the method never get here.
     */
    private static function openString(ZipArchive $zip, string $bytes): bool
    {
        return method_exists($zip, 'openString') && true === $zip->openString($bytes);
    }

    /**
     * The bytes of an archive written in memory, empty when it cannot be written.
     */
    private static function closeString(ZipArchive $zip): string
    {
        $bytes = method_exists($zip, 'closeString') ? $zip->closeString() : false;

        return \is_string($bytes) ? $bytes : '';
    }
}
