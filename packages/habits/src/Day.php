<?php

declare(strict_types=1);

namespace Phil\Habits;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\Habits\Exception\InvalidDay;

/**
 * A day of the calendar, without a time or a zone: the day a member lives a check-in on, in their own time zone.
 *
 * It is kept as the number of days since 1970-01-01, so that two days compare as integers and a day plus a number of
 * days is an addition. Years run from 1 to 9999.
 */
final readonly class Day
{
    private const int SECONDS_PER_DAY = 86_400;

    /** The number of 0001-01-01. */
    private const int FIRST = -719_162;

    /** The number of 9999-12-31. */
    private const int LAST = 2_932_896;

    private function __construct(
        public int $number,
    ) {
    }

    /**
     * Reads a day written as `YYYY-MM-DD`.
     *
     * @throws InvalidDay
     */
    public static function fromString(string $day): self
    {
        if (1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $day, $parts)) {
            throw InvalidDay::because('A day is written as YYYY-MM-DD.');
        }

        // The year 0000 is no year of the calendar for checkdate(), which counts from 1.
        [, $year, $month, $date] = array_map(\intval(...), $parts);

        if (!checkdate($month, $date, $year)) {
            throw InvalidDay::because('The day is not a day of the calendar.');
        }

        $midnight = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));
        \assert($midnight instanceof DateTimeImmutable);

        // Midnight in UTC is a whole number of days from the epoch, before it as after it.
        return new self(intdiv($midnight->getTimestamp(), self::SECONDS_PER_DAY));
    }

    /**
     * The day an instant falls on in a time zone.
     */
    public static function of(DateTimeInterface $instant, DateTimeZone $zone): self
    {
        return self::fromString(DateTimeImmutable::createFromInterface($instant)->setTimezone($zone)->format('Y-m-d'));
    }

    /**
     * The day of a number of days since 1970-01-01.
     *
     * @throws InvalidDay
     */
    public static function fromNumber(int $number): self
    {
        if ($number < self::FIRST || $number > self::LAST) {
            throw InvalidDay::because('A day runs from 0001-01-01 to 9999-12-31.');
        }

        return new self($number);
    }

    public function plus(int $days): self
    {
        return self::fromNumber($this->number + $days);
    }

    /**
     * The number of days from this day to another: positive when the other is later.
     */
    public function daysUntil(self $other): int
    {
        return $other->number - $this->number;
    }

    public function isBefore(self $other): bool
    {
        return $this->number < $other->number;
    }

    public function isAfter(self $other): bool
    {
        return $this->number > $other->number;
    }

    public function isSameAs(self $other): bool
    {
        return $this->number === $other->number;
    }

    /**
     * The day of the week, from 1 for Monday to 7 for Sunday, as ISO 8601 numbers them.
     *
     * @return int<1, 7>
     */
    public function weekday(): int
    {
        // 1970-01-01 was a Thursday, day 4.
        return (($this->number + 3) % 7 + 7) % 7 + 1;
    }

    /**
     * The Monday of this day's week.
     */
    public function weekStart(): self
    {
        return self::fromNumber($this->number - $this->weekday() + 1);
    }

    /**
     * The first day of this day's month.
     */
    public function monthStart(): self
    {
        return self::fromNumber($this->number - $this->dayOfMonth() + 1);
    }

    /**
     * The last day of this day's month.
     */
    public function monthEnd(): self
    {
        return self::fromNumber($this->monthStart()->number + $this->daysInMonth() - 1);
    }

    public function dayOfMonth(): int
    {
        return (int) $this->midnight()->format('j');
    }

    public function daysInMonth(): int
    {
        return (int) $this->midnight()->format('t');
    }

    /**
     * The day written as `YYYY-MM-DD`.
     */
    public function toString(): string
    {
        return $this->midnight()->format('Y-m-d');
    }

    private function midnight(): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.($this->number * self::SECONDS_PER_DAY));
    }
}
