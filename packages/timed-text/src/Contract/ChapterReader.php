<?php

declare(strict_types=1);

namespace Phil\TimedText\Contract;

use Phil\TimedText\ChapterList;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;

/**
 * Reads one format of chapters. A reader keeps no state between calls.
 */
interface ChapterReader
{
    /**
     * @throws InvalidTimedText at the first problem
     * @throws LimitExceeded    when the document is larger than the limits allow
     */
    public function read(string $input): ChapterList;
}
