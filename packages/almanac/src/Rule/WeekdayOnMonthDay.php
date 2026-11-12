<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeZone;
use Override;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Recurrence\Weekday;

/**
 * Every day of a month that falls on a weekday — `friday 13` —: none to three a year.
 */
final readonly class WeekdayOnMonthDay implements WithinTheYear
{
    public function __construct(
        public Weekday $weekday,
        public int $day,
    ) {
        if ($day < 1 || $day > 31) {
            throw InvalidRule::because(\sprintf('A day of the month is 1 to 31, not %d.', $day));
        }
    }

    #[Override]
    public function in(int $year, DateTimeZone $zone): array
    {
        $days = [];

        for ($month = 1; $month <= 12; ++$month) {
            if (checkdate($month, $this->day, $year) && Weekday::of($day = Days::of($year, $month, $this->day, $zone)) === $this->weekday) {
                $days[] = $day;
            }
        }

        return $days;
    }

    #[Override]
    public function isInstant(): bool
    {
        return false;
    }

    #[Override]
    public function __toString(): string
    {
        return strtolower($this->weekday->name).' '.$this->day;
    }
}
