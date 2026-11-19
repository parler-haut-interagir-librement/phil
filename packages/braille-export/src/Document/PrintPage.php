<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Document;

use Phil\BrailleExport\Contract\Block;

/**
 * Where a page of the print edition starts, so that a reader finds the page a sighted reader names: its number is
 * written at the end of the first line of each braille page, and a print page that starts within a braille page is
 * shown by a line of dots 3-6 ending with its number.
 */
final readonly class PrintPage implements Block
{
    /**
     * @param string $number the number as printed, such as `12` or `iv`
     */
    public function __construct(public string $number)
    {
    }
}
