<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Document;

use Phil\BrailleExport\Contract\Block;

/**
 * The next block starts a new braille page; nothing when the page is still empty.
 */
final readonly class PageBreak implements Block
{
}
