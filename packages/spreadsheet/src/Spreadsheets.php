<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

use Phil\Spreadsheet\Contract\SpreadsheetReader;
use Phil\Spreadsheet\Contract\SpreadsheetWriter;
use Phil\Spreadsheet\Csv\CsvDialect;
use Phil\Spreadsheet\Csv\CsvReader;
use Phil\Spreadsheet\Csv\CsvWriter;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Ods\OdsReader;
use Phil\Spreadsheet\Ods\OdsWriter;
use Phil\Spreadsheet\Xlsx\XlsxReader;
use Phil\Spreadsheet\Xlsx\XlsxWriter;

/**
 * The writer and the reader of a format, chosen by the format, an extension, a file name or a media type, all set
 * with the same limits, CSV dialect and temporary directory. It keeps no state between two calls: one instance serves
 * any number of files.
 */
final readonly class Spreadsheets
{
    /**
     * @param ReadLimits  $limits             the limits of every reader
     * @param CsvDialect  $csvDialect         the dialect of the CSV reader and writer
     * @param string|null $temporaryDirectory where the XLSX and ODS writers put their temporary files, the system's
     *                                        temporary directory when null
     */
    public function __construct(
        private ReadLimits $limits = new ReadLimits(),
        private CsvDialect $csvDialect = new CsvDialect(),
        private ?string $temporaryDirectory = null,
    ) {
    }

    /**
     * @param Format|string $format a format, or an extension, a file name or a media type that names one
     *
     * @throws InvalidSpreadsheet when the text names none of the formats
     */
    public function writer(Format|string $format): SpreadsheetWriter
    {
        return match (\is_string($format) ? Format::detect($format) : $format) {
            Format::Csv => new CsvWriter($this->csvDialect),
            Format::Xlsx => new XlsxWriter($this->temporaryDirectory),
            Format::Ods => new OdsWriter($this->temporaryDirectory),
        };
    }

    /**
     * @param Format|string $format a format, or an extension, a file name or a media type that names one
     *
     * @throws InvalidSpreadsheet when the text names none of the formats
     */
    public function reader(Format|string $format): SpreadsheetReader
    {
        return match (\is_string($format) ? Format::detect($format) : $format) {
            Format::Csv => new CsvReader($this->csvDialect, $this->limits),
            Format::Xlsx => new XlsxReader($this->limits),
            Format::Ods => new OdsReader($this->limits),
        };
    }

    /**
     * Writes a workbook to a file, in the format its extension names unless one is given.
     *
     * @throws InvalidSpreadsheet when the extension names no format
     * @throws WriteFailed        when the file cannot be opened, or the writer fails
     */
    public function writeFile(Workbook $workbook, string $path, ?Format $format = null, WriteOptions $options = new WriteOptions()): void
    {
        $writer = $this->writer($format ?? $path);
        $stream = @fopen($path, 'w');

        if (false === $stream) {
            throw WriteFailed::because('The file cannot be opened for writing.');
        }

        try {
            $writer->write($workbook, $stream, $options);
        } finally {
            fclose($stream);
        }
    }
}
