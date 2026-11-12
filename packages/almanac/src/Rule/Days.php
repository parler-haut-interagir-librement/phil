<?php

declare(strict_types=1);

namespace Phil\Almanac\Rule;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Midnights in a time zone — the first moment of the day where the clocks skip midnight.
 *
 * @internal
 */
final class Days
{
    public static function of(int $year, int $month, int $day, DateTimeZone $zone): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $zone)->setDate($year, $month, $day)->setTime(0, 0);
    }

    /**
     * The year a moment falls in, in a time zone.
     */
    public static function year(DateTimeInterface $at, DateTimeZone $zone): int
    {
        return (int) DateTimeImmutable::createFromInterface($at)->setTimezone($zone)->format('Y');
    }
}
