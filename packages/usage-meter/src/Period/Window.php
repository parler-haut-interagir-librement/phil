<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Period;

use DateTimeImmutable;

/**
 * The stretch of time a quota covers at one moment, and the counter that receives what is counted then.
 *
 * For a calendar or billing period, the window is the period and its single counter. For a rolling window, the
 * counters are buckets: the window runs from the oldest bucket still inside it to the end of the current bucket.
 */
final readonly class Window
{
    public function __construct(
        /** The first instant counted. */
        public DateTimeImmutable $start,
        /** When the window next changes: a new period starts, or the oldest bucket leaves it. */
        public DateTimeImmutable $end,
        /** The start of the counter that receives what is counted now. */
        public DateTimeImmutable $counterStart,
        /** When that counter stops counting in any window, and may be purged. */
        public DateTimeImmutable $counterEnd,
    ) {
    }

    /**
     * The window of a period that is its own single counter.
     */
    public static function period(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        return new self($start, $end, $start, $end);
    }
}
