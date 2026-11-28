<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Internal;

use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Exception\WriteFailed;
use ZipArchive;

/**
 * The streams and the temporary files of the writers.
 *
 * @internal
 */
final class Output
{
    /**
     * 1980-01-02, one day after the first a zip entry dates, so that no time zone moves it before: every entry carries
     * it, so the same workbook gives the same bytes.
     */
    private const int ENTRY_TIME = 315619200;

    /**
     * @return resource
     *
     * @throws InvalidSpreadsheet when the value is not an open stream
     */
    public static function stream(mixed $stream): mixed
    {
        if (!\is_resource($stream) || 'stream' !== get_resource_type($stream)) {
            throw InvalidSpreadsheet::because('A spreadsheet is written to an open stream.');
        }

        return $stream;
    }

    public static function temporary(string $directory): string
    {
        // tempnam() falls back on the system's directory when the one given cannot hold the file: it is checked first.
        $path = is_dir($directory) && is_writable($directory) ? @tempnam($directory, 'phil-spreadsheet-') : false;

        if (false === $path) {
            throw WriteFailed::because(\sprintf('No temporary file can be made in "%s".', $directory));
        }

        return $path;
    }

    public static function remove(string ...$paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Zips the parts in their order: texts and files, the first text stored without compression when asked, as the
     * `mimetype` of an OpenDocument file must be.
     *
     * @param array<string, string> $texts the parts held as text, by name
     * @param array<string, string> $files the parts held in files, by name
     */
    public static function zip(string $target, array $texts, array $files, bool $storeFirst = false): void
    {
        $zip = new ZipArchive();

        if (true !== $zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
            throw WriteFailed::because('The temporary file of the archive cannot be opened.');
        }

        $first = true;

        foreach ($texts as $name => $text) {
            $zip->addFromString($name, $text);
            $zip->setMtimeName($name, self::ENTRY_TIME);

            if ($first && $storeFirst) {
                $zip->setCompressionName($name, ZipArchive::CM_STORE);
            }

            $first = false;
        }

        foreach ($files as $name => $path) {
            if (!@$zip->addFile($path, $name)) {
                $zip->close();

                throw WriteFailed::because(\sprintf('The part "%s" cannot be added to the archive.', $name));
            }

            $zip->setMtimeName($name, self::ENTRY_TIME);
        }

        if (!@$zip->close()) {
            throw WriteFailed::because('The archive cannot be written to its temporary file.');
        }
    }

    /**
     * @param resource $stream
     */
    public static function copy(string $path, mixed $stream): void
    {
        $file = @fopen($path, 'r');

        if (false === $file) {
            throw WriteFailed::because('The temporary file of the archive cannot be read.');
        }

        try {
            if (@stream_copy_to_stream($file, $stream) !== filesize($path)) {
                throw WriteFailed::because('The stream refused the file.');
            }
        } finally {
            fclose($file);
        }
    }
}
