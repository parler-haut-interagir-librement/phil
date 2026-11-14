<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Application\Export;

/**
 * The rows of a table to export: the headings of its columns, how many rows match, and the rows, read page by page as
 * they are written.
 */
final readonly class ExportedRows
{
    /**
     * @param list<string>          $headings
     * @param iterable<list<mixed>> $rows
     */
    public function __construct(
        public string $title,
        public array $headings,
        public int $total,
        public iterable $rows,
    ) {
    }
}
