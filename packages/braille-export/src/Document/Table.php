<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Document;

use Phil\BrailleExport\Contract\Block;

/**
 * A table, linearized: a braille line cannot hold its columns, so each row becomes one entry starting in cell 1 and
 * running over in cell 3, its cells separated by semicolons, each preceded by the heading of its column when the
 * first row holds the headings.
 */
final readonly class Table implements Block
{
    /**
     * @param list<list<string>> $rows      the rows, each a list of the texts of its cells
     * @param bool               $headerRow whether the first row holds the headings of the columns
     * @param string             $caption   a caption, laid out as a paragraph before the rows
     */
    public function __construct(
        public array $rows,
        public bool $headerRow = true,
        public string $caption = '',
    ) {
    }
}
