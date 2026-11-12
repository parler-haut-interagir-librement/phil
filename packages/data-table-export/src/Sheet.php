<?php

declare(strict_types=1);

namespace Phil\DataTableExport;

/**
 * What is written: a title, the headings of the columns, and the rows, read once and in order, so that a generator
 * keeps one row in memory at a time. A cell holds nothing (`null`), a boolean, an integer, a float, a text, a date, a
 * backed enum — written as its value — or a `Stringable`.
 */
final readonly class Sheet
{
    /**
     * @param list<string>          $headings the first row, none when empty
     * @param iterable<list<mixed>> $rows     each a list of cells
     */
    public function __construct(
        public string $title,
        public array $headings,
        public iterable $rows,
    ) {
    }
}
