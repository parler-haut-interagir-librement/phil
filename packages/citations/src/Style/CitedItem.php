<?php

declare(strict_types=1);

namespace Phil\Citations\Style;

use Phil\Citations\Cite;

/**
 * One cite of a citation, with its bibliography entry, and whether the text cites the reference here for the first
 * time.
 */
final readonly class CitedItem
{
    public function __construct(
        public Entry $entry,
        public Cite $cite,
        public bool $first,
    ) {
    }
}
