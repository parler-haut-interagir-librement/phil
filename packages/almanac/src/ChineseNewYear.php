<?php

declare(strict_types=1);

namespace Phil\Almanac;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Astronomy\JulianDay;
use Phil\Almanac\Astronomy\MoonPhase;
use Phil\Almanac\Astronomy\MoonPhases;
use Phil\Almanac\Astronomy\Phase;
use Phil\Almanac\Astronomy\Season;
use Phil\Almanac\Astronomy\Seasons;
use Phil\Almanac\Astronomy\Sun;
use Phil\Almanac\Exception\OutOfRange;

/**
 * The Chinese New Year, the first day of the first month of the lunisolar calendar, by the rules of the calendar
 * as China has computed it since 1645: days in China's time; the eleventh month holds the December solstice; a year
 * of thirteen months, from one eleventh month to the next, makes its first month without a principal term — the Sun
 * entering a sign — a leap month. The new year is two months after the eleventh, three when a leap eleventh or
 * twelfth falls between, as in 2033. The year's animal and element follow the cycles of
 * twelve and ten.
 */
final class ChineseNewYear
{
    public const int FIRST_YEAR = 1001;

    public const int LAST_YEAR = 2999;

    public const array ANIMALS = ['rat', 'ox', 'tiger', 'rabbit', 'dragon', 'snake', 'horse', 'goat', 'monkey', 'rooster', 'dog', 'pig'];

    public const array ELEMENTS = ['wood', 'fire', 'earth', 'metal', 'water'];

    /** @var array<int, DateTimeImmutable> the years computed, each once: a year depends on nothing else */
    private static array $years = [];

    /**
     * The day the year starts, at midnight in China's time, from 1001 to 2999: the year reads the December solstice
     * before it and the new moons after the next, which the sky gives from 1000 to 3000. A year is computed once, then
     * kept for the process.
     *
     * @throws OutOfRange
     */
    public static function of(int $year): DateTimeImmutable
    {
        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            throw OutOfRange::year($year, 'The Chinese New Year', self::FIRST_YEAR, self::LAST_YEAR);
        }

        self::$years[$year] ??= self::computed($year);

        return self::$years[$year];
    }

    private static function computed(int $year): DateTimeImmutable
    {
        $china = new DateTimeZone('Asia/Shanghai');
        $day = static fn (DateTimeImmutable $at): DateTimeImmutable => new DateTimeImmutable($at->setTimezone($china)->format('Y-m-d'), $china);
        $solstice = $day(Seasons::of($year - 1, Season::DecemberSolstice));
        $next = $day(Seasons::of($year, Season::DecemberSolstice));
        $starts = array_map(static fn (MoonPhase $moon): DateTimeImmutable => $day($moon->at), MoonPhases::between($solstice->modify('-65 days'), $next->modify('+35 days'), [Phase::New]));
        // An eleventh month starts on the day of the last new moon on or before its solstice's day.
        $eleventhOf = static fn (DateTimeImmutable $solstice): int => \count(array_filter($starts, static fn (DateTimeImmutable $start): bool => $start <= $solstice)) - 1;
        $eleventh = $eleventhOf($solstice);
        $first = self::hasPrincipalTerm($starts[$eleventh + 1], $starts[$eleventh + 2]);
        // Only a leap twelfth month, which no year of the range has, would make this one count.
        $second = self::hasPrincipalTerm($starts[$eleventh + 2], $starts[$eleventh + 3]);

        return $starts[$eleventh + self::monthsToNewYear(13 === $eleventhOf($next) - $eleventh, $first, $second)];
    }

    /**
     * How many months after the eleventh's start the year starts: two, or three when a year of thirteen months makes
     * the month after the eleventh — or the one after that — a leap month, the first without a principal term.
     *
     * @internal
     */
    public static function monthsToNewYear(bool $thirteenMonths, bool $firstHasTerm, bool $secondHasTerm): int
    {
        return $thirteenMonths && (!$firstHasTerm || !$secondHasTerm) ? 3 : 2;
    }

    /**
     * Whether the Sun enters a sign — its longitude crosses a multiple of 30° — between two moments: less than 30°
     * pass in a month, and going past 360° is entering Aries.
     *
     * @internal
     */
    public static function hasPrincipalTerm(DateTimeImmutable $from, DateTimeImmutable $until): bool
    {
        $start = Sun::longitude(JulianDay::fromUtc($from));
        $end = Sun::longitude(JulianDay::fromUtc($until));

        return $end < $start || floor($start / 30) !== floor($end / 30);
    }

    /**
     * The year's animal: rat, ox, tiger… pig.
     */
    public static function animal(int $year): string
    {
        return self::ANIMALS[(($year - 4) % 12 + 12) % 12];
    }

    /**
     * The year's element: wood, fire, earth, metal or water, each for two years.
     */
    public static function element(int $year): string
    {
        return self::ELEMENTS[intdiv((($year - 4) % 10 + 10) % 10, 2)];
    }
}
