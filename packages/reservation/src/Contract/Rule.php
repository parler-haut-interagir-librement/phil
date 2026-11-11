<?php

declare(strict_types=1);

namespace Phil\Reservation\Contract;

use DateTimeZone;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Violation;

/**
 * A rule a booking request follows, whatever else is booked: a notice, a length, a grid. The application adds its own
 * — a holder's quota, a role — by implementing it.
 */
interface Rule
{
    /**
     * The reason the request breaks the rule, or `null` when it follows it.
     *
     * @param DateTimeZone $timezone the resource's, for rules read on its clock
     */
    public function check(BookingRequest $request, DateTimeZone $timezone): ?Violation;
}
