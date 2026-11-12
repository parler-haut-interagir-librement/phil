<?php

declare(strict_types=1);

namespace Phil\Almanac\Tests\Unit\Astronomy;

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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JulianDay::class)]
#[CoversClass(Seasons::class)]
#[CoversClass(Season::class)]
#[CoversClass(MoonPhases::class)]
#[CoversClass(MoonPhase::class)]
#[CoversClass(Phase::class)]
#[CoversClass(Sun::class)]
#[CoversClass(OutOfRange::class)]
final class AstronomyTest extends TestCase
{
    /**
     * Meeus, example 27.a: the June solstice of 1962, JDE 2437837.39245.
     */
    public function testTheExampleOfMeeusForASolstice(): void
    {
        self::assertEqualsWithDelta(2437837.39245, Seasons::jde(1962, Season::JuneSolstice), 0.000005);
    }

    /**
     * The seasons of 2026 as the United States Naval Observatory publishes them, to the minute.
     */
    public function testTheSeasonsOf2026(): void
    {
        $this->assertNear('2026-03-20 14:46', Seasons::of(2026, Season::MarchEquinox));
        $this->assertNear('2026-06-21 08:24', Seasons::of(2026, Season::JuneSolstice));
        $this->assertNear('2026-09-23 00:05', Seasons::of(2026, Season::SeptemberEquinox));
        $this->assertNear('2026-12-21 20:50', Seasons::of(2026, Season::DecemberSolstice));
        $this->assertNear('2000-03-20 07:35', Seasons::of(2000, Season::MarchEquinox));
        self::assertSame('UTC', Seasons::of(2026, Season::MarchEquinox)->getTimezone()->getName());
        self::assertSame(['march equinox', 'june solstice', 'september equinox', 'december solstice'], array_map(static fn (Season $season): string => $season->value, Season::cases()));
    }

    public function testTheSeasonsHaveTheirYears(): void
    {
        self::assertSame('1000-03', Seasons::of(1000, Season::MarchEquinox)->format('Y-m'));
        self::assertSame('3000-12', Seasons::of(3000, Season::DecemberSolstice)->format('Y-m'));

        foreach ([999, 3001] as $year) {
            try {
                Seasons::of($year, Season::JuneSolstice);
                self::fail(\sprintf('%d is out of range.', $year));
            } catch (OutOfRange $outOfRange) {
                self::assertSame(\sprintf('A season is computed for the years 1000 to 3000, not %d.', $year), $outOfRange->getMessage());
            }
        }
    }

    /**
     * Meeus, example 49.a: the new moon of February 1977, k = −283, JDE 2443192.65118.
     */
    public function testTheExampleOfMeeusForANewMoon(): void
    {
        self::assertEqualsWithDelta(2443192.65118, MoonPhases::jde(-283, Phase::New), 0.000005);
    }

    /**
     * The phases of January and February 2026 as the United States Naval Observatory publishes them.
     */
    public function testThePhasesOfTheMoon(): void
    {
        $phases = MoonPhases::between(new DateTimeImmutable('2026-01-01 00:00 UTC'), new DateTimeImmutable('2026-03-01 00:00 UTC'));
        $expected = [
            ['full moon', '2026-01-03 10:03'], ['last quarter', '2026-01-10 15:48'], ['new moon', '2026-01-18 19:52'], ['first quarter', '2026-01-26 04:47'],
            ['full moon', '2026-02-01 22:09'], ['last quarter', '2026-02-09 12:43'], ['new moon', '2026-02-17 12:01'], ['first quarter', '2026-02-24 12:28'],
        ];

        self::assertCount(8, $phases);

        foreach ($phases as $index => $phase) {
            self::assertSame($expected[$index][0], $phase->phase->value);
            $this->assertNear($expected[$index][1], $phase->at);
        }

        self::assertSame([0.0, 0.25, 0.5, 0.75], array_map(static fn (Phase $phase): float => $phase->fraction(), Phase::cases()));
    }

    public function testAPeriodIncludesItsStartAndNotItsEnd(): void
    {
        $full = MoonPhases::between(new DateTimeImmutable('2026-01-03 10:02:50 UTC'), new DateTimeImmutable('2026-02-01 22:09:12 UTC'), [Phase::Full]);

        self::assertSame(['2026-01-03 10:02:50'], array_map(static fn (MoonPhase $phase): string => $phase->at->format('Y-m-d H:i:s'), $full));
        self::assertSame([], MoonPhases::between(new DateTimeImmutable('2026-01-04 UTC'), new DateTimeImmutable('2026-01-10 UTC'), [Phase::Full, Phase::New]));
        self::assertCount(13, MoonPhases::between(new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC'), [Phase::Full]));
        self::assertCount(12, MoonPhases::between(new DateTimeImmutable('2026-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC'), [Phase::New]));
        self::assertCount(1, MoonPhases::between(new DateTimeImmutable('1000-06-01 UTC'), new DateTimeImmutable('1000-07-01 UTC'), [Phase::FirstQuarter]));
        self::assertCount(1, MoonPhases::between(new DateTimeImmutable('2999-06-01 UTC'), new DateTimeImmutable('2999-07-01 UTC'), [Phase::LastQuarter]));
    }

    /**
     * The edges of the lunations searched: a last quarter an hour after the period starts, and the new moon of
     * October 2026, more than half a day before its mean instant, a few seconds before the period ends.
     */
    public function testTheEdgesOfASearch(): void
    {
        self::assertSame(['last quarter 2026-01-10 15:48:23'], array_map(static fn (MoonPhase $phase): string => $phase->phase->value.' '.$phase->at->format('Y-m-d H:i:s'), MoonPhases::between(new DateTimeImmutable('2026-01-10 14:48 UTC'), new DateTimeImmutable('2026-01-11 UTC'), [Phase::LastQuarter])));
        self::assertSame(['new moon 2026-10-10 15:49:56'], array_map(static fn (MoonPhase $phase): string => $phase->phase->value.' '.$phase->at->format('Y-m-d H:i:s'), MoonPhases::between(new DateTimeImmutable('2026-10-10 15:49:56 UTC'), new DateTimeImmutable('2026-10-10 15:50:00 UTC'), [Phase::New])));
    }

    public function testThePhasesHaveTheirYears(): void
    {
        $this->expectException(OutOfRange::class);
        $this->expectExceptionMessageIsOrContains('A phase of the Moon is computed for the years 1000 to 3000, not 3001.');

        MoonPhases::jde(12381, Phase::New);
    }

    public function testThePhasesHaveTheirFirstYear(): void
    {
        $this->expectExceptionMessageIsOrContains('not 999.');

        MoonPhases::jde(-12370, Phase::Full);
    }

    /**
     * Meeus, example 25.a: the Sun on 1992 October 13.0 TD, apparent longitude 199.90895° in low accuracy.
     */
    public function testTheExampleOfMeeusForTheSun(): void
    {
        self::assertEqualsWithDelta(199.90895, Sun::longitude(2448908.5), 0.0001);
        self::assertEqualsWithDelta(0.0, fmod(Sun::longitude(Seasons::jde(2026, Season::MarchEquinox)) + 180, 360) - 180, 0.01);
        self::assertEqualsWithDelta(270.0, Sun::longitude(Seasons::jde(2026, Season::DecemberSolstice)), 0.01);
        self::assertGreaterThanOrEqual(0.0, Sun::longitude(Seasons::jde(2026, Season::MarchEquinox) - 0.5));
        self::assertLessThan(360.0, Sun::longitude(Seasons::jde(2026, Season::MarchEquinox) - 0.5));
    }

    /**
     * ΔT from the polynomials of Espenak and Meeus, each range at its anchor, and the instants they correct.
     */
    public function testDeltaT(): void
    {
        foreach ([1800 => -18.72, 1900 => -2.79, 1920 => 21.20, 1950 => 29.07, 1975 => 45.45, 2000 => 63.86, 2005 => 64.69, 2025 => 74.47, 2050 => 93.0, 2150 => 328.0] as $year => $seconds) {
            self::assertEqualsWithDelta($seconds, JulianDay::deltaT($year), 0.6, (string) $year);
        }

        self::assertEqualsWithDelta(JulianDay::deltaT(1940.999), JulianDay::deltaT(1941.0), 0.1);
        self::assertEqualsWithDelta(JulianDay::deltaT(1985.999), JulianDay::deltaT(1986.0), 0.1);
        self::assertEqualsWithDelta(JulianDay::deltaT(2049.999), JulianDay::deltaT(2050.0), 0.1);
        self::assertSame(17.0, JulianDay::poly(2, [1, 2, 3]));
        self::assertSame('2000-01-01 11:58:56', JulianDay::toUtc(2451545.0)->format('Y-m-d H:i:s'));
        self::assertEqualsWithDelta(2451545.0, JulianDay::fromUtc(new DateTimeImmutable('2000-01-01 11:58:56', new DateTimeZone('UTC'))), 0.00001);
        self::assertEqualsWithDelta(2461103.5 + 75.2 / 86400, JulianDay::fromUtc(new DateTimeImmutable('2026-03-04 00:00 UTC')), 0.00002);
    }

    private function assertNear(string $expected, DateTimeImmutable $actual): void
    {
        $difference = abs($actual->getTimestamp() - new DateTimeImmutable($expected.' UTC')->getTimestamp());

        self::assertLessThanOrEqual(60, $difference, \sprintf('%s is %d seconds from %s.', $actual->format('Y-m-d H:i:s'), $difference, $expected));
    }
}
