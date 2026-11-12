<?php

declare(strict_types=1);

namespace Phil\Almanac\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\ChineseNewYear;
use Phil\Almanac\Easter;
use Phil\Almanac\Exception\OutOfRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Easter::class)]
#[CoversClass(ChineseNewYear::class)]
final class CalendarsTest extends TestCase
{
    public function testWesternEaster(): void
    {
        $known = [1583 => '04-10', 1818 => '03-22', 1943 => '04-25', 1961 => '04-02', 2000 => '04-23', 2008 => '03-23', 2011 => '04-24', 2019 => '04-21', 2024 => '03-31', 2025 => '04-20', 2026 => '04-05', 2027 => '03-28', 2038 => '04-25', 2285 => '03-22', 4099 => '04-19'];

        foreach ($known as $year => $date) {
            self::assertSame($year.'-'.$date, Easter::western($year)->format('Y-m-d'), (string) $year);
        }

        $toronto = Easter::western(2026, new DateTimeZone('America/Toronto'));
        self::assertSame(['2026-04-05 00:00', 'America/Toronto'], [$toronto->format('Y-m-d H:i'), $toronto->getTimezone()->getName()]);
    }

    public function testOrthodoxEaster(): void
    {
        $known = [1583 => '04-10', 1900 => '04-22', 2000 => '04-30', 2008 => '04-27', 2010 => '04-04', 2023 => '04-16', 2024 => '05-05', 2025 => '04-20', 2026 => '04-12', 2027 => '05-02', 2100 => '05-02', 4099 => '05-03'];

        foreach ($known as $year => $date) {
            self::assertSame($year.'-'.$date, Easter::orthodox($year)->format('Y-m-d'), (string) $year);
        }

        self::assertSame('00:00 Europe/Athens', Easter::orthodox(2026, new DateTimeZone('Europe/Athens'))->format('H:i e'));
    }

    /**
     * Every year of the range against Gauss's algorithm, with its two exceptions, independent of the one the library
     * uses; the Orthodox date from Gauss's Julian form, moved by the Gregorian leap days the Julian calendar kept.
     */
    public function testEveryYearAgainstGauss(): void
    {
        for ($year = Easter::FIRST_YEAR; $year <= Easter::LAST_YEAR; ++$year) {
            $century = intdiv($year, 100);
            $p = intdiv(13 + 8 * $century, 25);
            $q = intdiv($century, 4);
            $m = (15 - $p + $century - $q) % 30;
            $n = (4 + $century - $q) % 7;
            $d = (19 * ($year % 19) + $m) % 30;
            $e = (2 * ($year % 4) + 4 * ($year % 7) + 6 * $d + $n) % 7;
            $days = 22 + $d + $e;
            $days = match (true) {
                29 === $d && 6 === $e => 50,
                28 === $d && 6 === $e && (11 * $m + 11) % 30 < 19 => 49,
                default => $days,
            };
            self::assertSame(\sprintf('%d-%s', $year, $days > 31 ? \sprintf('04-%02d', $days - 31) : \sprintf('03-%02d', $days)), Easter::western($year)->format('Y-m-d'));

            $julianD = (19 * ($year % 19) + 15) % 30;
            $julianE = (2 * ($year % 4) + 4 * ($year % 7) + 6 * $julianD + 6) % 7;
            $march = new DateTimeImmutable(\sprintf('%d-03-22', $year))->modify(\sprintf('+%d days', $julianD + $julianE + $century - $q - 2));
            self::assertSame($march->format('Y-m-d'), Easter::orthodox($year)->format('Y-m-d'), (string) $year);
        }
    }

    public function testEasterHasItsYears(): void
    {
        foreach ([[1582, true], [4100, true], [1582, false], [4100, false]] as [$year, $orthodox]) {
            try {
                $orthodox ? Easter::orthodox($year) : Easter::western($year);
                self::fail((string) $year);
            } catch (OutOfRange $outOfRange) {
                self::assertSame(\sprintf('Easter is computed for the years 1583 to 4099, not %d.', $year), $outOfRange->getMessage());
            }
        }
    }

    /**
     * The new years of 1985 to 2050 as China's calendar gives them, the leap eleventh month of 2033 among them.
     */
    public function testTheChineseNewYear(): void
    {
        $known = [1985 => '02-20', 1990 => '01-27', 1996 => '02-19', 2000 => '02-05', 2001 => '01-24', 2004 => '01-22', 2006 => '01-29', 2010 => '02-14', 2012 => '01-23', 2014 => '01-31', 2015 => '02-19', 2017 => '01-28', 2018 => '02-16', 2019 => '02-05', 2020 => '01-25', 2021 => '02-12', 2022 => '02-01', 2023 => '01-22', 2024 => '02-10', 2025 => '01-29', 2026 => '02-17', 2027 => '02-06', 2028 => '01-26', 2029 => '02-13', 2030 => '02-03', 2031 => '01-23', 2032 => '02-11', 2033 => '01-31', 2034 => '02-19', 2035 => '02-08', 2036 => '01-28', 2037 => '02-15', 2038 => '02-04', 2039 => '01-24', 2040 => '02-12', 2041 => '02-01', 2042 => '01-22', 2043 => '02-10', 2044 => '01-30', 2045 => '02-17', 2046 => '02-06', 2047 => '01-26', 2048 => '02-14', 2049 => '02-02', 2050 => '01-23'];

        foreach ($known as $year => $date) {
            self::assertSame($year.'-'.$date, ChineseNewYear::of($year)->format('Y-m-d'), (string) $year);
        }

        self::assertSame('00:00:00 Asia/Shanghai', ChineseNewYear::of(2026)->format('H:i:s e'));
        self::assertSame(['1001', '2999'], [ChineseNewYear::of(ChineseNewYear::FIRST_YEAR)->format('Y'), ChineseNewYear::of(ChineseNewYear::LAST_YEAR)->format('Y')]);

        foreach ([1000, 3000] as $year) {
            try {
                ChineseNewYear::of($year);
                self::fail((string) $year);
            } catch (OutOfRange $outOfRange) {
                self::assertStringContainsString('The Chinese New Year', $outOfRange->getMessage());
                self::assertStringContainsString('1001', $outOfRange->getMessage());
                self::assertStringContainsString('2999', $outOfRange->getMessage());
            }
        }
    }

    /**
     * The leap month moves the new year only in a year of thirteen months, when it is one of the two months after the
     * eleventh.
     */
    public function testALeapMonth(): void
    {
        self::assertSame([2, 2, 2, 2, 2, 3, 3, 3], [
            ChineseNewYear::monthsToNewYear(false, true, true), ChineseNewYear::monthsToNewYear(false, false, true),
            ChineseNewYear::monthsToNewYear(false, true, false), ChineseNewYear::monthsToNewYear(false, false, false),
            ChineseNewYear::monthsToNewYear(true, true, true), ChineseNewYear::monthsToNewYear(true, false, true),
            ChineseNewYear::monthsToNewYear(true, true, false), ChineseNewYear::monthsToNewYear(true, false, false),
        ]);
    }

    /**
     * The leap eleventh month of 2033 has no principal term; the month of the March equinox has one, entering Aries.
     */
    public function testAPrincipalTerm(): void
    {
        $china = new DateTimeZone('Asia/Shanghai');
        $day = static fn (string $date): DateTimeImmutable => new DateTimeImmutable($date, $china);

        self::assertFalse(ChineseNewYear::hasPrincipalTerm($day('2033-12-22'), $day('2034-01-20')), 'the leap eleventh month');
        self::assertTrue(ChineseNewYear::hasPrincipalTerm($day('2033-11-22'), $day('2033-12-22')), 'the eleventh, with the solstice');
        self::assertTrue(ChineseNewYear::hasPrincipalTerm($day('2026-03-05'), $day('2026-03-21')), 'past 360°, just after the equinox');
        self::assertFalse(ChineseNewYear::hasPrincipalTerm($day('2026-03-10'), $day('2026-03-15')), 'in Pisces, past its middle');
        self::assertFalse(ChineseNewYear::hasPrincipalTerm($day('2026-03-25'), $day('2026-04-15')), 'in Aries');
        self::assertFalse(ChineseNewYear::hasPrincipalTerm($day('2026-03-25'), $day('2026-03-25')), 'no time at all');
    }

    public function testTheAnimalAndTheElement(): void
    {
        self::assertSame(['horse', 'fire'], [ChineseNewYear::animal(2026), ChineseNewYear::element(2026)]);
        self::assertSame(['dragon', 'wood'], [ChineseNewYear::animal(2024), ChineseNewYear::element(2024)]);
        self::assertSame(['rat', 'metal'], [ChineseNewYear::animal(2020), ChineseNewYear::element(2020)]);
        self::assertSame(['pig', 'water'], [ChineseNewYear::animal(1983), ChineseNewYear::element(1983)]);
        self::assertSame(['dragon', 'fire'], [ChineseNewYear::animal(-4), ChineseNewYear::element(-4)]);
        self::assertSame(['rat', 'wood'], [ChineseNewYear::animal(4), ChineseNewYear::element(4)]);
        self::assertCount(12, ChineseNewYear::ANIMALS);
        self::assertCount(5, ChineseNewYear::ELEMENTS);
    }
}
