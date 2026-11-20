<?php

declare(strict_types=1);

namespace Phil\UsageMeter\Period;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Override;
use Phil\UsageMeter\Exception\InvalidUsage;

/**
 * Days, ISO weeks, months or years of a calendar, from local midnight in one time zone: every subject starts again
 * on the first of the month together.
 */
final readonly class CalendarPeriod implements PeriodPolicy
{
    public function __construct(
        public CalendarUnit $unit,
        public DateTimeZone $timezone = new DateTimeZone('UTC'),
    ) {
    }

    public static function day(string $timezone = 'UTC'): self
    {
        return new self(CalendarUnit::Day, self::zone($timezone));
    }

    public static function week(string $timezone = 'UTC'): self
    {
        return new self(CalendarUnit::Week, self::zone($timezone));
    }

    public static function month(string $timezone = 'UTC'): self
    {
        return new self(CalendarUnit::Month, self::zone($timezone));
    }

    public static function year(string $timezone = 'UTC'): self
    {
        return new self(CalendarUnit::Year, self::zone($timezone));
    }

    #[Override]
    public function windowAt(DateTimeImmutable $at, ?DateTimeImmutable $anchor = null): Window
    {
        $local = $at->setTimezone($this->timezone);
        $year = (int) $local->format('Y');

        [$start, $length] = match ($this->unit) {
            CalendarUnit::Day => [$local, 'P1D'],
            CalendarUnit::Week => [$local->setISODate((int) $local->format('o'), (int) $local->format('W')), 'P1W'],
            CalendarUnit::Month => [$local->setDate($year, (int) $local->format('n'), 1), 'P1M'],
            CalendarUnit::Year => [$local->setDate($year, 1, 1), 'P1Y'],
        };
        $start = $start->setTime(0, 0);

        return Window::period($start, $start->add(new DateInterval($length)));
    }

    private static function zone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidUsage::because(\sprintf('"%s" is not a time zone.', $timezone));
        }
    }
}
