<?php

declare(strict_types=1);

namespace Phil\Reservation;

use DateTimeImmutable;
use Phil\Reservation\Exception\InvalidDefinition;

/**
 * When a booking's holder is reminded of it: so many minutes before its start, a day and an hour. A reminder whose
 * moment passed unsent is overtaken by the next one: a booking made an hour ahead gets the hour's reminder only.
 */
final readonly class ReminderSchedule
{
    /** The most reminders a booking gets. */
    public const int MAX_REMINDERS = 20;

    /** The furthest a reminder comes before the start: a year. */
    public const int MAX_MINUTES = 525_600;

    /** @var list<int> the minutes before the start, the furthest first */
    public array $offsets;

    /**
     * @throws InvalidDefinition when an offset is out of bounds or there are too many
     */
    public function __construct(int ...$offsets)
    {
        $offsets = array_unique($offsets);
        rsort($offsets);

        if (\count($offsets) > self::MAX_REMINDERS) {
            throw InvalidDefinition::because(\sprintf('A booking gets at most %d reminders.', self::MAX_REMINDERS));
        }

        foreach ($offsets as $offset) {
            if ($offset < 1 || $offset > self::MAX_MINUTES) {
                throw InvalidDefinition::because(\sprintf('A reminder comes from 1 to %d minutes before the start, not %d.', self::MAX_MINUTES, $offset));
            }
        }

        $this->offsets = $offsets;
    }

    /**
     * The moments of the reminders of a booking, the earliest first, in the time zone of its start.
     *
     * @return list<DateTimeImmutable>
     */
    public function moments(Interval $booking): array
    {
        return array_map(static fn (int $offset): DateTimeImmutable => $booking->start->modify(\sprintf('-%d minutes', $offset)), $this->offsets);
    }

    /**
     * The reminder to send now, by its offset, or `null`: the one closest to the start whose moment has come, after the
     * last one sent, before the booking starts.
     *
     * @param ?int $lastSent the offset of the last reminder sent, `null` when none was
     */
    public function due(Interval $booking, DateTimeImmutable $now, ?int $lastSent = null): ?int
    {
        $moment = $now->getTimestamp();

        if ($moment >= $booking->from) {
            return null;
        }

        $due = null;

        foreach ($this->offsets as $offset) {
            if ($booking->from - $offset * 60 <= $moment && (null === $lastSent || $offset < $lastSent)) {
                $due = $offset;
            }
        }

        return $due;
    }
}
