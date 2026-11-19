<?php

declare(strict_types=1);

namespace Phil\Habits;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Habits\Exception\InvalidCheckIn;

/**
 * What a member did with a habit on one of their days, and the instant it was recorded, in UTC.
 *
 * A habit holds at most one check-in per day: a new one replaces it. A partial check-in carries the value done towards
 * the habit's target; a done one may carry it too, for the record.
 */
final readonly class CheckIn
{
    public DateTimeImmutable $recordedAt;

    /**
     * @throws InvalidCheckIn
     */
    public function __construct(
        public string $habit,
        public Day $day,
        public CheckInStatus $status,
        public ?int $value,
        DateTimeImmutable $recordedAt,
    ) {
        $this->recordedAt = $recordedAt->setTimezone(new DateTimeZone('UTC'));

        if (null !== $value && ($value < 0 || $value > Habit::MAX_TARGET * 1000)) {
            throw InvalidCheckIn::because('The value of a check-in is a whole number from 0 to a thousand times the largest target.');
        }

        if (CheckInStatus::Partial === $status && null === $value) {
            throw InvalidCheckIn::because('A partial check-in carries the value done.');
        }

        if ($status->isExcused() && null !== $value) {
            throw InvalidCheckIn::because('A skipped or frozen day carries no value.');
        }
    }

    /**
     * The units the check-in brings to its period, out of the habit's units per day.
     */
    public function units(int $unitsPerDay): int
    {
        return match ($this->status) {
            CheckInStatus::Done => $unitsPerDay,
            CheckInStatus::Partial => min((int) $this->value, $unitsPerDay),
            CheckInStatus::Skipped, CheckInStatus::Frozen => 0,
        };
    }
}
