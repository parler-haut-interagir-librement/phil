<?php

declare(strict_types=1);

namespace Phil\TimedText\Contract;

use Phil\TimedText\ChapterList;

/**
 * Writes chapters in one format. A writer keeps no state between calls.
 */
interface ChapterWriter
{
    public function write(ChapterList $chapters): string;
}
