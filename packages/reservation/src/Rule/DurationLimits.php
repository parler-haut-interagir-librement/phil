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
 * A booking lasts between a shortest and a longest length, in minutes; without a longest, as long as it likes.
 */
final readonly class DurationLimits implements Rule
{
    /**
     * @throws InvalidDefinition when the shortest is below one minute or the longest below the shortest
     */
    public function __construct(
        public int $minimum = 1,
        public ?int $maximum = null,
    ) {
        if ($minimum < 1) {
            throw InvalidDefinition::because(\sprintf('The shortest booking lasts at least one minute, not %d.', $minimum));
        }

        if (null !== $maximum && $maximum < $minimum) {
            throw InvalidDefinition::because(\sprintf('The longest booking, %d minutes, is not shorter than the shortest, %d.', $maximum, $minimum));
        }
    }

    #[Override]
    public function check(BookingRequest $request, DateTimeZone $timezone): ?Violation
    {
        $seconds = $request->interval->seconds();

        if ($seconds < $this->minimum * 60) {
            return new Violation(ViolationCode::TooShort, ['minutes' => $this->minimum]);
        }

        return null !== $this->maximum && $seconds > $this->maximum * 60
            ? new Violation(ViolationCode::TooLong, ['minutes' => $this->maximum])
            : null;
    }
}
