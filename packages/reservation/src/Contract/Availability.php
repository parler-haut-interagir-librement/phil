<?php

declare(strict_types=1);

namespace Phil\Reservation\Contract;

use Phil\Reservation\Exception\InvalidInterval;
use Phil\Reservation\Interval;

/**
 * When a resource can be booked: the spans it is open, read for a period.
 */
interface Availability
{
    /**
     * The spans it is open that meet the period, in order, neither overlapping nor touching — two spans that touch are
     * one. A span that starts before the period or ends after it is given beyond it, so that a span holding a booking
     * whole is one window.
     *
     * @return list<Interval>
     *
     * @throws InvalidInterval when the period is longer than the availability reads
     */
    public function windows(Interval $period): array;
}
