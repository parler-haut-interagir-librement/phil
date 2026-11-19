<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Document;

use InvalidArgumentException;
use Phil\BrailleExport\Contract\Block;

/**
 * A list: each item starts in cell 1 and runs over in cell 3, two cells further in for each level of nesting; an
 * ordered list writes the number of each item before its text.
 */
final readonly class ListBlock implements Block
{
    /**
     * @param list<ListItem> $items
     * @param int            $start the number of the first item of an ordered list
     */
    public function __construct(
        public array $items,
        public bool $ordered = false,
        public int $start = 1,
    ) {
        if ($start < 0) {
            throw new InvalidArgumentException('An ordered list starts at 0 or more.');
        }
    }
}
