<?php

declare(strict_types=1);

namespace Phil\Citations\Style;

use Phil\Citations\Model\Reference;

/**
 * A reference as it stands in the bibliography of a text: its number there, from 1, and the letter that tells it
 * from another of the same authors and year (« 2020a »), empty when none is needed.
 */
final readonly class Entry
{
    public function __construct(
        public Reference $reference,
        public int $number,
        public string $yearSuffix = '',
    ) {
    }
}
