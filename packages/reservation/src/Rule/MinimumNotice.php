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
 * A booking starts at least so many minutes after it is asked for; at zero, it never starts in the past.
 */
final readonly class MinimumNotice implements Rule
{
    /**
     * @throws InvalidDefinition when the notice is negative
     */
    public function __construct(
        public int $minutes,
    ) {
        if ($minutes < 0) {
            throw InvalidDefinition::because(\sprintf('A notice is not negative: %d minutes.', $minutes));
        }
    }

    #[Override]
    public function check(BookingRequest $request, DateTimeZone $timezone): ?Violation
    {
        return $request->interval->from - $request->requestedAt->getTimestamp() < $this->minutes * 60
            ? new Violation(ViolationCode::TooSoon, ['minutes' => $this->minutes])
            : null;
    }
}
