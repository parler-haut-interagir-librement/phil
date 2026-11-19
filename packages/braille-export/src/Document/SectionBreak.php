<?php

declare(strict_types=1);

namespace Phil\BrailleExport\Document;

use Phil\BrailleExport\Contract\Block;

/**
 * The next block starts a new section, on a new braille page: a `section` of a PEF file.
 */
final readonly class SectionBreak implements Block
{
}
