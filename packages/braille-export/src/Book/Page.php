<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Book;

/**
 * One braille page, laid out: its rows of Unicode braille cells, page numbers included, no row ending with a blank
 * cell.
 */
final readonly class Page
{
    /**
     * @param list<string> $rows      the rows from the top, each of at most the cells of a line
     * @param int          $number    the braille page number, from 1
     * @param string|null  $printPage the print page shown on the page, if any
     */
    public function __construct(
        public array $rows,
        public int $number,
        public ?string $printPage = null,
    ) {
    }
}
