<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Csv;

use Generator;
use Override;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\Contract\SpreadsheetReader;
use Phil\Spreadsheet\Exception\ReadRefused;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Internal\RowBuilder;
use Phil\Spreadsheet\ReadLimits;
use Phil\Spreadsheet\Row;

/**
 * Reads a CSV file in UTF-8, record by record, within the limits: memory stays that of one record.
 *
 * A CSV file has no types, so every cell read is a text — the caller states the types; an empty field is an empty
 * cell. A byte order mark at the start is skipped. A record's number counts the records, blank lines included. The
 * file is the one sheet, named after the file without its extension.
 */
final readonly class CsvReader implements SpreadsheetReader
{
    private const string BYTE_ORDER_MARK = "\u{FEFF}";

    public function __construct(
        private CsvDialect $dialect = new CsvDialect(),
        private ReadLimits $limits = new ReadLimits(),
    ) {
    }

    #[Override]
    public function format(): Format
    {
        return Format::Csv;
    }

    #[Override]
    public function sheetNames(string $path): array
    {
        $this->size($path);

        return [pathinfo($path, \PATHINFO_FILENAME)];
    }

    #[Override]
    public function rows(string $path, ?string $sheet = null): Generator
    {
        if (null !== $sheet && $sheet !== pathinfo($path, \PATHINFO_FILENAME)) {
            throw ReadRefused::because(\sprintf('The CSV file has no sheet "%s".', mb_scrub(mb_substr($sheet, 0, 100), 'UTF-8')));
        }

        $this->size($path);
        $file = @fopen($path, 'r');

        if (false === $file) {
            throw ReadRefused::because('The file cannot be read.');
        }

        try {
            if (self::BYTE_ORDER_MARK !== fread($file, 3)) {
                rewind($file);
            }

            $builder = new RowBuilder($this->limits);
            $number = 0;

            while (false !== ($fields = fgetcsv($file, null, $this->dialect->delimiter, $this->dialect->enclosure, ''))) {
                ++$number;

                if ([null] === $fields) {
                    continue;
                }

                if (\count($fields) > $this->limits->maxColumns) {
                    throw ReadRefused::overLimit(\sprintf('The number of cells of the record %d', $number), $this->limits->maxColumns);
                }

                $cells = [];

                foreach ($fields as $field) {
                    $field = (string) $field;

                    if (!mb_check_encoding($field, 'UTF-8')) {
                        throw ReadRefused::because(\sprintf('The record %d is not written in UTF-8.', $number));
                    }

                    $cells[] = Cell::text($field);
                }

                $row = $builder->row($number, $cells);

                if ($row instanceof Row) {
                    yield $row;
                }
            }
        } finally {
            fclose($file);
        }
    }

    private function size(string $path): void
    {
        $size = is_file($path) ? filesize($path) : false;

        if (false === $size) {
            throw ReadRefused::because('The file cannot be read.');
        }

        if ($size > $this->limits->maxFileBytes) {
            throw ReadRefused::overLimit('The size of the file', $this->limits->maxFileBytes);
        }
    }
}
