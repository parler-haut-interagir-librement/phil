<?php

declare(strict_types=1);

namespace Phil\Almanac\Tests\Unit\Astronomy;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Astronomy\Eclipses;
use Phil\Almanac\Astronomy\JulianDay;
use Phil\Almanac\Astronomy\LunarEclipse;
use Phil\Almanac\Astronomy\LunarEclipseKind;
use Phil\Almanac\Astronomy\SolarEclipse;
use Phil\Almanac\Astronomy\SolarEclipseKind;
use Phil\Almanac\Exception\OutOfRange;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Eclipses::class)]
#[CoversClass(SolarEclipse::class)]
#[CoversClass(LunarEclipse::class)]
#[CoversClass(SolarEclipseKind::class)]
#[CoversClass(LunarEclipseKind::class)]
#[CoversClass(JulianDay::class)]
#[CoversClass(OutOfRange::class)]
final class EclipsesTest extends TestCase
{
    /**
     * Meeus, example 54.a: the partial solar eclipse of 1993 May 21, γ = 1.1348, u = 0.0097, magnitude 0.740;
     * example 54.b: the penumbral lunar eclipse of 1973 June 15, γ = −1.3249, penumbral magnitude 0.4625.
     */
    public function testTheExamplesOfMeeus(): void
    {
        $solar = Eclipses::solarAt(-82);
        self::assertInstanceOf(SolarEclipse::class, $solar);
        self::assertSame(SolarEclipseKind::Partial, $solar->kind);
        self::assertFalse($solar->central);
        self::assertEqualsWithDelta(1.1348, $solar->gamma, 0.0001);
        self::assertEqualsWithDelta(0.740, $solar->magnitude, 0.0005);
        self::assertSame('1993-05-21', $solar->at->format('Y-m-d'));

        $lunar = Eclipses::lunarAt(-328.5);
        self::assertInstanceOf(LunarEclipse::class, $lunar);
        self::assertSame(LunarEclipseKind::Penumbral, $lunar->kind);
        self::assertEqualsWithDelta(-1.3249, $lunar->gamma, 0.0001);
        self::assertEqualsWithDelta(0.4625, $lunar->penumbralMagnitude, 0.0001);
        self::assertLessThan(0, $lunar->umbralMagnitude);
        self::assertNull($lunar->partial);
        self::assertNull($lunar->total);
        self::assertSame('1973-06-15', $lunar->at->format('Y-m-d'));
        // 101 minutes either side of the greatest, as Meeus computes.
        self::assertEqualsWithDelta(101 * 60, $lunar->at->getTimestamp() - $lunar->penumbral[0]->getTimestamp(), 60);
        self::assertEqualsWithDelta(101 * 60, $lunar->penumbral[1]->getTimestamp() - $lunar->at->getTimestamp(), 60);

        // No eclipse at a new moon far from a node, nor at the next full moon.
        self::assertNull(Eclipses::solarAt(-81));
        self::assertNull(Eclipses::lunarAt(-80.5));
    }

    /**
     * The solar eclipses from 2022 to 2026, with their greatest in universal time, their kind and their γ, as NASA's
     * canon of eclipses gives them; the greatest within three minutes, γ within 0.002, the magnitude within 0.01.
     */
    public function testTheSolarEclipsesOfAFewYears(): void
    {
        $expected = [
            ['2022-04-30 20:41', SolarEclipseKind::Partial, -1.1901, 0.6396],
            ['2022-10-25 11:00', SolarEclipseKind::Partial, 1.0701, 0.8619],
            ['2023-04-20 04:17', SolarEclipseKind::Hybrid, -0.3952, null],
            ['2023-10-14 17:59', SolarEclipseKind::Annular, 0.3753, null],
            ['2024-04-08 18:17', SolarEclipseKind::Total, 0.3431, null],
            ['2024-10-02 18:45', SolarEclipseKind::Annular, -0.3509, null],
            ['2025-03-29 10:47', SolarEclipseKind::Partial, 1.0405, 0.9376],
            ['2025-09-21 19:42', SolarEclipseKind::Partial, -1.0651, 0.8550],
            ['2026-02-17 12:12', SolarEclipseKind::Annular, -0.9743, null],
            ['2026-08-12 17:46', SolarEclipseKind::Total, 0.8977, null],
        ];

        $eclipses = Eclipses::solar(new DateTimeImmutable('2022-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC'));
        self::assertCount(\count($expected), $eclipses);

        foreach ($expected as $index => [$at, $kind, $gamma, $magnitude]) {
            $eclipse = $eclipses[$index];
            self::assertEqualsWithDelta(new DateTimeImmutable($at.' UTC')->getTimestamp(), $eclipse->at->getTimestamp(), 180, $at);
            self::assertSame($kind, $eclipse->kind, $at);
            self::assertSame(null === $magnitude, $eclipse->central, $at);
            self::assertEqualsWithDelta($gamma, $eclipse->gamma, 0.002, $at);
            self::assertEqualsWithDelta($magnitude, $eclipse->magnitude, 0.01, $at);
            self::assertSame('UTC', $eclipse->at->getTimezone()->getName());
        }
    }

    /**
     * The lunar eclipses from 2022 to 2026, their greatest, kind and umbral magnitude as NASA's canon gives them.
     */
    public function testTheLunarEclipsesOfAFewYears(): void
    {
        $expected = [
            ['2022-05-16 04:11', LunarEclipseKind::Total, 1.4137],
            ['2022-11-08 10:59', LunarEclipseKind::Total, 1.3589],
            ['2023-05-05 17:23', LunarEclipseKind::Penumbral, -0.0459],
            ['2023-10-28 20:14', LunarEclipseKind::Partial, 0.1220],
            ['2024-03-25 07:12', LunarEclipseKind::Penumbral, -0.1304],
            ['2024-09-18 02:44', LunarEclipseKind::Partial, 0.0848],
            ['2025-03-14 06:58', LunarEclipseKind::Total, 1.1784],
            ['2025-09-07 18:11', LunarEclipseKind::Total, 1.3638],
            ['2026-03-03 11:33', LunarEclipseKind::Total, 1.1526],
            ['2026-08-28 04:12', LunarEclipseKind::Partial, 0.9299],
        ];

        $eclipses = Eclipses::lunar(new DateTimeImmutable('2022-01-01 UTC'), new DateTimeImmutable('2027-01-01 UTC'));
        self::assertCount(\count($expected), $eclipses);

        foreach ($expected as $index => [$at, $kind, $umbral]) {
            $eclipse = $eclipses[$index];
            self::assertEqualsWithDelta(new DateTimeImmutable($at.' UTC')->getTimestamp(), $eclipse->at->getTimestamp(), 180, $at);
            self::assertSame($kind, $eclipse->kind, $at);
            self::assertEqualsWithDelta($umbral, $eclipse->umbralMagnitude, 0.015, $at);
            self::assertGreaterThan(0, $eclipse->penumbralMagnitude);
        }
    }

    /**
     * The phases of the total lunar eclipse of 2025 September 7: penumbral 15:28 to 20:55, partial 16:27 to 19:57,
     * total 17:31 to 18:53, as NASA gives them.
     */
    public function testThePhasesOfALunarEclipse(): void
    {
        [$eclipse] = Eclipses::lunar(new DateTimeImmutable('2025-09-01 UTC'), new DateTimeImmutable('2025-09-30 UTC'));

        $phases = [
            ['15:28', $eclipse->penumbral[0]], ['20:55', $eclipse->penumbral[1]],
            ['16:27', $eclipse->partial[0] ?? null], ['19:57', $eclipse->partial[1] ?? null],
            ['17:31', $eclipse->total[0] ?? null], ['18:53', $eclipse->total[1] ?? null],
        ];

        foreach ($phases as [$expected, $actual]) {
            self::assertNotNull($actual);
            self::assertEqualsWithDelta(new DateTimeImmutable('2025-09-07 '.$expected.' UTC')->getTimestamp(), $actual->getTimestamp(), 180, $expected);
        }

        [$partial] = Eclipses::lunar(new DateTimeImmutable('2024-09-01 UTC'), new DateTimeImmutable('2024-09-30 UTC'));
        self::assertNotNull($partial->partial);
        self::assertNull($partial->total);
    }

    public function testTheBoundsOfAPeriod(): void
    {
        $at = Eclipses::solar(new DateTimeImmutable('2024-04-01 UTC'), new DateTimeImmutable('2024-05-01 UTC'))[0]->at;

        self::assertCount(1, Eclipses::solar($at, $at->modify('+1 second')));
        self::assertSame([], Eclipses::solar($at->modify('-1 day'), $at));
        self::assertSame([], Eclipses::solar($at, $at));
        self::assertSame([], Eclipses::solar($at->modify('+1 day'), $at));

        $moon = Eclipses::lunar(new DateTimeImmutable('2025-03-01 UTC'), new DateTimeImmutable('2025-04-01 UTC'))[0]->at;
        self::assertCount(1, Eclipses::lunar($moon, $moon->modify('+1 second')));
        self::assertSame([], Eclipses::lunar($moon->modify('-1 day'), $moon));
        self::assertCount(1, Eclipses::lunar($moon->setTimezone(new DateTimeZone('Asia/Tokyo')), $moon->modify('+1 second')));

        // Over a whole century, between two and five solar eclipses a year.
        $solar = Eclipses::solar(new DateTimeImmutable('2001-01-01 UTC'), new DateTimeImmutable('2101-01-01 UTC'));
        self::assertCount(224, $solar);
        self::assertCount(68, array_filter($solar, static fn (SolarEclipse $eclipse): bool => SolarEclipseKind::Total === $eclipse->kind));
    }

    public function testTheRangeOfYears(): void
    {
        self::assertGreaterThan(1, \count(Eclipses::solar(new DateTimeImmutable('1000-01-01 UTC'), new DateTimeImmutable('1001-01-01 UTC'))));
        self::assertGreaterThan(1, \count(Eclipses::lunar(new DateTimeImmutable('2999-12-01 UTC'), new DateTimeImmutable('3000-12-01 UTC'))));

        foreach ([['0998-01-01', '0999-06-01'], ['3001-01-01', '3002-01-01']] as [$from, $until]) {
            try {
                Eclipses::lunar(new DateTimeImmutable($from.' UTC'), new DateTimeImmutable($until.' UTC'));
                self::fail('Out of range.');
            } catch (OutOfRange $outOfRange) {
                self::assertStringContainsString('An eclipse is computed for the years 1000 to 3000', $outOfRange->getMessage());
            }
        }
    }
}
