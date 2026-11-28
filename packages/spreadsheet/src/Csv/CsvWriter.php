<?php

declare(strict_types=1);

namespace Phil\Spreadsheet\Csv;

use DateTimeImmutable;
use Override;
use Phil\Spreadsheet\Cell;
use Phil\Spreadsheet\CellType;
use Phil\Spreadsheet\Contract\SpreadsheetWriter;
use Phil\Spreadsheet\Exception\InvalidSpreadsheet;
use Phil\Spreadsheet\Exception\WriteFailed;
use Phil\Spreadsheet\Format;
use Phil\Spreadsheet\Internal\FormulaGuard;
use Phil\Spreadsheet\Internal\Output;
use Phil\Spreadsheet\Internal\SheetRows;
use Phil\Spreadsheet\Internal\Values;
use Phil\Spreadsheet\Workbook;
use Phil\Spreadsheet\WriteOptions;

/**
 * Writes a workbook of one sheet as CSV, straight to the stream, row by row: memory stays that of one row.
 *
 * A CSV file has no types: a number is written as `12.5`, a boolean as `TRUE` or `FALSE`, a date as `2026-11-21` and
 * a date and time as `2026-11-21 14:30:00`. A text a spreadsheet would run as a formula gets an apostrophe in front
 * unless the options turn the guard off. A failure half way leaves the rows already written in the stream.
 */
final readonly class CsvWriter implements SpreadsheetWriter
{
    public function __construct(
        private CsvDialect $dialect = new CsvDialect(),
    ) {
    }

    #[Override]
    public function format(): Format
    {
        return Format::Csv;
    }

    #[Override]
    public function write(Workbook $workbook, mixed $stream, WriteOptions $options = new WriteOptions()): void
    {
        $stream = Output::stream($stream);

        if (\count($workbook->sheets) > 1) {
            throw InvalidSpreadsheet::because('A CSV file holds one sheet.');
        }

        $sheet = $workbook->sheets[0];

        if ($this->dialect->byteOrderMark) {
            $this->put($stream, "\u{FEFF}");
        }

        $headings = SheetRows::headings($sheet, $options);

        if (null !== $headings) {
            $this->row($stream, $headings, $options->guardFormulas);
        }

        foreach (SheetRows::data($sheet, $options) as $cells) {
            $this->row($stream, $cells, $options->guardFormulas);
        }
    }

    /**
     * A cell as the text of a CSV file.
     */
    public static function text(Cell $cell, bool $guardFormulas = true): string
    {
        $value = $cell->value;

        return match (true) {
            null === $value => '',
            \is_string($value) => $guardFormulas && FormulaGuard::isRisky($value) ? "'".$value : $value,
            \is_bool($value) => $value ? 'TRUE' : 'FALSE',
            $value instanceof DateTimeImmutable => CellType::Date === $cell->type ? Values::date($value) : Values::date($value, ' '),
            default => Values::number($value),
        };
    }

    /**
     * @param resource   $stream
     * @param list<Cell> $cells
     */
    private function row(mixed $stream, array $cells, bool $guardFormulas): void
    {
        $fields = [];

        foreach ($cells as $cell) {
            $fields[] = self::text($cell, $guardFormulas);
        }

        if (false === @fputcsv($stream, $fields, $this->dialect->delimiter, $this->dialect->enclosure, '', $this->dialect->lineEnding)) {
            throw WriteFailed::because('The stream refused a row.');
        }
    }

    /**
     * @param resource $stream
     */
    private function put(mixed $stream, string $bytes): void
    {
        if (\strlen($bytes) !== @fwrite($stream, $bytes)) {
            throw WriteFailed::because('The stream refused the byte order mark.');
        }
    }
}
