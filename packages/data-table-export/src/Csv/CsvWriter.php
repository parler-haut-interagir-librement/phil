<?php

declare(strict_types=1);

namespace Phil\DataTableExport\Csv;

use DateTimeInterface;
use Override;
use Phil\DataTableExport\Cells;
use Phil\DataTableExport\Contract\ExportWriter;
use Phil\DataTableExport\Exception\ExportRefused;
use Phil\DataTableExport\Format;
use Phil\DataTableExport\Sheet;

/**
 * Writes a sheet as CSV, after RFC 4180: UTF-8, fields quoted when they need it, a quote doubled, lines ended by CRLF.
 * The rows are written as they are read, so memory stays that of one row whatever their number.
 *
 * By default the file starts with a byte order mark, which spreadsheets read as UTF-8, and every text goes through
 * `FormulaGuard`, so that a cell typed by someone else never runs as a formula. A boolean is written `1` or `0`, a date
 * `2026-11-12` or `2026-11-12 14:30:00`, a float without a thousands separator and with a point, whatever the locale.
 * The title of the sheet is not written: a CSV file has none.
 */
final readonly class CsvWriter implements ExportWriter
{
    private const string BOM = "\u{FEFF}";

    /**
     * @param string $separator     one byte, neither a quote nor an end of line: `,`, `;` or a tab
     * @param bool   $byteOrderMark whether the file starts with the UTF-8 byte order mark
     * @param bool   $guardFormulas whether a text that a spreadsheet would run is kept as text
     *
     * @throws ExportRefused when the separator is not one byte, or is a quote or an end of line
     */
    public function __construct(
        private string $separator = ',',
        private bool $byteOrderMark = true,
        private bool $guardFormulas = true,
    ) {
        if (1 !== \strlen($separator) || str_contains("\"\r\n", $separator)) {
            throw ExportRefused::because('The separator of a CSV file is one byte, neither a quote nor an end of line.');
        }
    }

    #[Override]
    public function format(): Format
    {
        return Format::Csv;
    }

    #[Override]
    public function write(Sheet $sheet, mixed $stream): void
    {
        if ($this->byteOrderMark) {
            $this->put($stream, self::BOM);
        }

        if ([] !== $sheet->headings) {
            $this->line($stream, $sheet->headings);
        }

        foreach ($sheet->rows as $row) {
            $this->line($stream, $row);
        }
    }

    /**
     * @param resource    $stream
     * @param list<mixed> $cells
     */
    private function line(mixed $stream, array $cells): void
    {
        $fields = array_map($this->field(...), $cells);

        if (false === @fputcsv($stream, $fields, $this->separator, '"', '', "\r\n")) {
            throw ExportRefused::because('The stream refused a line of the CSV file.');
        }
    }

    private function field(mixed $cell): string
    {
        $value = Cells::value($cell);

        return match (true) {
            null === $value => '',
            \is_bool($value) => $value ? '1' : '0',
            \is_int($value), \is_float($value) => (string) $value,
            $value instanceof DateTimeInterface => Cells::date($value),
            default => $this->guardFormulas ? FormulaGuard::protect($value) : $value,
        };
    }

    /**
     * @param resource $stream
     */
    private function put(mixed $stream, string $bytes): void
    {
        if (false === @fwrite($stream, $bytes)) {
            throw ExportRefused::because('The stream refused the CSV file.');
        }
    }
}
