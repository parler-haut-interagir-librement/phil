<?php

declare(strict_types=1);

namespace Phil\Spreadsheet;

use Phil\Spreadsheet\Exception\InvalidSpreadsheet;

/**
 * The sheets of a file to write, in order, each with a name of its own.
 */
final readonly class Workbook
{
    /** @var non-empty-list<Sheet> */
    public array $sheets;

    /**
     * @throws InvalidSpreadsheet when there is no sheet, or two sheets share a name, whatever its case
     */
    public function __construct(Sheet ...$sheets)
    {
        $sheets = array_values($sheets);

        if ([] === $sheets) {
            throw InvalidSpreadsheet::because('A workbook holds at least one sheet.');
        }

        $names = [];

        foreach ($sheets as $sheet) {
            $key = mb_strtolower($sheet->name, 'UTF-8');

            if (isset($names[$key])) {
                throw InvalidSpreadsheet::because(\sprintf('Two sheets are named "%s".', $sheet->name));
            }

            $names[$key] = true;
        }

        $this->sheets = $sheets;
    }

    /**
     * A workbook of one sheet.
     *
     * @param iterable<array<mixed>> $rows
     * @param list<Column>           $columns
     */
    public static function single(string $name, iterable $rows, array $columns = []): self
    {
        return new self(new Sheet($name, $rows, $columns));
    }
}
