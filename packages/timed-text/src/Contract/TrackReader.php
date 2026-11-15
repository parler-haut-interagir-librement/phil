<?php

declare(strict_types=1);

namespace Phil\TimedText\Contract;

use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Inspection;
use Phil\TimedText\Track;

/**
 * Reads one format into a track. A reader keeps no state between calls.
 */
interface TrackReader
{
    /**
     * Reads a document that follows its format, and refuses it at the first problem.
     *
     * @throws InvalidTimedText at the first problem, with its line when the format has lines
     * @throws LimitExceeded    when the document is larger than the limits allow
     */
    public function read(string $input): Track;

    /**
     * Reads what can be read of a document, steps over the blocks it cannot, and lists their problems.
     *
     * @throws InvalidTimedText when the document is not of the format at all
     * @throws LimitExceeded    when the document is larger than the limits allow, or has more problems
     */
    public function inspect(string $input): Inspection;
}
