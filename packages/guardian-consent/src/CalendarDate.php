<?php

declare(strict_types=1);

namespace Phil\GuardianConsent;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\GuardianConsent\Exception\InvalidConsent;

/**
 * A day of the Gregorian calendar, without a time or a time zone: a date of birth, the day a person reaches an age.
 * Dates are compared by their numbers, never as objects.
 */
final readonly class CalendarDate
{
    private function __construct(
        public int $year,
        public int $month,
        public int $day,
    ) {
    }

    /**
     * @throws InvalidConsent when the text is not `YYYY-MM-DD`, or not a day of the calendar from year 1 to 9999
     */
    public static function fromString(string $text): self
    {
        if (1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $text, $match)) {
            throw InvalidConsent::because('A date is written YYYY-MM-DD.');
        }

        [, $year, $month, $day] = $match;

        return self::fromParts((int) $year, (int) $month, (int) $day);
    }

    /**
     * @throws InvalidConsent when the parts are not a day of the calendar from year 1 to 9999
     */
    public static function fromParts(int $year, int $month, int $day): self
    {
        if ($year < 1 || $year > 9999 || !checkdate($month, $day, $year)) {
            throw InvalidConsent::because(\sprintf('%04d-%02d-%02d is not a day of the calendar.', $year, $month, $day));
        }

        return new self($year, $month, $day);
    }

    /**
     * The day an instant falls on in a time zone.
     */
    public static function of(DateTimeInterface $instant, DateTimeZone $timeZone): self
    {
        $local = DateTimeImmutable::createFromInterface($instant)->setTimezone($timeZone);

        return new self((int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j'));
    }

    public static function isLeapYear(int $year): bool
    {
        return 0 === $year % 4 && (0 !== $year % 100 || 0 === $year % 400);
    }

    /**
     * The day `$years` years after this one; 29 February falls on the day the rule names in a year without one.
     *
     * @throws InvalidConsent when the year passes 9999
     */
    public function anniversary(int $years, LeapDayRule $rule = LeapDayRule::FirstOfMarch): self
    {
        $year = $this->year + $years;

        if (2 === $this->month && 29 === $this->day && !self::isLeapYear($year)) {
            return LeapDayRule::FirstOfMarch === $rule ? self::fromParts($year, 3, 1) : self::fromParts($year, 2, 28);
        }

        return self::fromParts($year, $this->month, $this->day);
    }

    /**
     * The whole years completed from this day to `$on`: 0 until the first anniversary, and 0 for a day before this one.
     */
    public function yearsUntil(self $on, LeapDayRule $rule = LeapDayRule::FirstOfMarch): int
    {
        if (!$on->isAfter($this)) {
            return 0;
        }

        $years = $on->year - $this->year;

        return $on->isBefore($this->anniversary($years, $rule)) ? $years - 1 : $years;
    }

    /**
     * -1, 0 or 1 as this day comes before, on or after the other.
     */
    public function compare(self $other): int
    {
        return [$this->year, $this->month, $this->day] <=> [$other->year, $other->month, $other->day];
    }

    public function isBefore(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    public function isAfter(self $other): bool
    {
        return $this->compare($other) > 0;
    }

    public function isSameDay(self $other): bool
    {
        return 0 === $this->compare($other);
    }

    /**
     * The first instant of the day in a time zone.
     */
    public function startsAt(DateTimeZone $timeZone): DateTimeImmutable
    {
        return new DateTimeImmutable($this->toString(), $timeZone)->setTime(0, 0);
    }

    public function toString(): string
    {
        return \sprintf('%04d-%02d-%02d', $this->year, $this->month, $this->day);
    }
}
