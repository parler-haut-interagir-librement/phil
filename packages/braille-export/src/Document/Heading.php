<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Document;

use InvalidArgumentException;
use Phil\BrailleExport\Contract\Block;

/**
 * A heading: level 1 is centred, level 2 starts in cell 5, levels 3 to 6 in cell 7.
 */
final readonly class Heading implements Block
{
    public function __construct(public string $text, public int $level = 1)
    {
        if ($level < 1 || $level > 6) {
            throw new InvalidArgumentException('The level of a heading is between 1 and 6.');
        }
    }
}
