<?php

declare(strict_types=1);

namespace Phil\Reservation\Rule;

use DateTimeZone;
use Override;
use Phil\Reservation\BookingRequest;
use Phil\Reservation\Contract\Rule;
use Phil\Reservation\Exception\InvalidDefinition;
use Phil\Reservation\Violation;
use Phil\Reservation\ViolationCode;

/**
 * Bookings on a grid: they start on a multiple of so many minutes from midnight on the resource's clock, and last a
 * multiple of it. A grid of 30 minutes takes 9:00 and 9:30 for one hour, not 9:10.
 */
final readonly class SlotGrid implements Rule
{
    /**
     * @throws InvalidDefinition when the step is not a divisor of a day, from 1 to 1440 minutes
     */
    public function __construct(
        public int $minutes,
    ) {
        if ($minutes < 1 || 0 !== 1440 % $minutes) {
            throw InvalidDefinition::because(\sprintf('A grid step divides a day in minutes, from 1 to 1440: not %d.', $minutes));
        }
    }

    #[Override]
    public function check(BookingRequest $request, DateTimeZone $timezone): ?Violation
    {
        // A step divides a day: a start on its grid is a multiple of it from the Unix epoch read on the local clock.
        $step = $this->minutes * 60;
        $local = $request->interval->from + $timezone->getOffset($request->interval->start);
        $aligned = 0 === $local % $step && 0 === $request->interval->seconds() % $step;

        return $aligned ? null : new Violation(ViolationCode::OffGrid, ['minutes' => $this->minutes]);
    }
}
