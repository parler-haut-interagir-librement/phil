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
 * A booking starts at most so many minutes after it is asked for: a booking window that slides, sixty days ahead.
 */
final readonly class MaximumAdvance implements Rule
{
    /**
     * @throws InvalidDefinition when the advance is below one minute
     */
    public function __construct(
        public int $minutes,
    ) {
        if ($minutes < 1) {
            throw InvalidDefinition::because(\sprintf('A booking window is at least one minute, not %d.', $minutes));
        }
    }

    #[Override]
    public function check(BookingRequest $request, DateTimeZone $timezone): ?Violation
    {
        return $request->interval->from - $request->requestedAt->getTimestamp() > $this->minutes * 60
            ? new Violation(ViolationCode::TooFarAhead, ['minutes' => $this->minutes])
            : null;
    }
}
