<?php

declare(strict_types=1);

namespace Phil\Almanac;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Exception\OutOfRange;

/**
 * The date of Easter Sunday, as the churches compute it rather than from the sky: the Gregorian computus for the
 * Western churches (the anonymous algorithm Meeus gives in chapter 8), the Julian one for the Orthodox churches,
 * whose date is then written in the Gregorian calendar.
 */
final class Easter
{
    public const int FIRST_YEAR = 1583;

    public const int LAST_YEAR = 4099;

    /**
     * Western Easter Sunday, at midnight in a time zone.
     */
    public static function western(int $year, ?DateTimeZone $zone = null): DateTimeImmutable
    {
        self::check($year);
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return self::date($year, $month, $day, $zone);
    }

    /**
     * Orthodox Easter Sunday, computed in the Julian calendar and given in the Gregorian one, at midnight.
     */
    public static function orthodox(int $year, ?DateTimeZone $zone = null): DateTimeImmutable
    {
        self::check($year);
        $a = $year % 4;
        $b = $year % 7;
        $c = $year % 19;
        $d = (19 * $c + 15) % 30;
        $e = (2 * $a + 4 * $b - $d + 34) % 7;
        $month = intdiv($d + $e + 114, 31);
        $day = (($d + $e + 114) % 31) + 1;
        // The Julian calendar is behind by the Gregorian leap days it kept: 13 days from 1900 to 2099.
        $behind = intdiv($year, 100) - intdiv($year, 400) - 2;

        return self::date($year, $month, $day, $zone)->modify(\sprintf('+%d days', $behind));
    }

    private static function check(int $year): void
    {
        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            throw OutOfRange::year($year, 'Easter', self::FIRST_YEAR, self::LAST_YEAR);
        }
    }

    private static function date(int $year, int $month, int $day, ?DateTimeZone $zone): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $zone)->setDate($year, $month, $day)->setTime(0, 0);
    }
}
