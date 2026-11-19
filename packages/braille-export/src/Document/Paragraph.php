<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Document;

use Phil\BrailleExport\Contract\Block;

/**
 * A paragraph: its first line starts in cell 3, the next ones in cell 1. A line break of the text, `\n`, starts a new
 * line in cell 1.
 */
final readonly class Paragraph implements Block
{
    public function __construct(public string $text)
    {
    }
}
