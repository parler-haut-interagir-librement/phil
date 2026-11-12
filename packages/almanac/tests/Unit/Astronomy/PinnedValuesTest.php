<?php

declare(strict_types=1);

namespace Phil\Almanac\Tests\Unit\Astronomy;

use DateTimeImmutable;
use Phil\Almanac\Astronomy\JulianDay;
use Phil\Almanac\Astronomy\MoonPhases;
use Phil\Almanac\Astronomy\Phase;
use Phil\Almanac\Astronomy\Season;
use Phil\Almanac\Astronomy\Seasons;
use Phil\Almanac\Astronomy\Sun;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The computations, checked against Meeus's examples and the Naval Observatory in AstronomyTest, pinned here to a
 * thousandth of a second across their whole range: any change to a term of the algorithms shows.
 */
#[CoversClass(JulianDay::class)]
#[CoversClass(MoonPhases::class)]
#[CoversClass(Seasons::class)]
#[CoversClass(Sun::class)]
final class PinnedValuesTest extends TestCase
{
    private const float DAY = 1.0E-8;

    public function testThePhases(): void
    {
        foreach ([
            [-283, Phase::New, 2443192.651182683], [0, Phase::New, 2451550.260257791], [100.25, Phase::FirstQuarter, 2454510.649098951],
            [300.5, Phase::Full, 2460424.493209544], [500.75, Phase::LastQuarter, 2466336.929876916], [-500.25, Phase::FirstQuarter, 2436777.105211271],
            [-1000.75, Phase::LastQuarter, 2421997.280399931], [1200.5, Phase::Full, 2487001.326543894], [8000, Phase::New, 2687794.242440664],
            [-9000.5, Phase::Full, 2185760.470150731], [12368.5, Phase::New, 2816799.062547724],
        ] as [$k, $phase, $jde]) {
            self::assertEqualsWithDelta($jde, MoonPhases::jde($k, $phase), self::DAY, \sprintf('%s %s', $k, $phase->value));
        }
    }

    public function testTheSeasons(): void
    {
        foreach ([
            [1000, 2086381.485184561, 2086474.933730611, 2086568.082167313, 2086657.264341910],
            [1500, 2269002.639059320, 2269095.755837416, 2269189.181248015, 2269278.681143979],
            [1962, 2437744.604423451, 2437837.392448240, 2437931.025373773, 2438020.844061738],
            [2026, 2461120.115799663, 2461212.851445060, 2461306.504626282, 2461396.369009301],
            [2500, 2634245.014800230, 2634337.386564752, 2634431.197422796, 2634521.418077562],
            [3000, 2816866.227939401, 2816958.203969009, 2817052.117561777, 2817142.720973755],
        ] as [$year, $march, $june, $september, $december]) {
            self::assertEqualsWithDelta($march, Seasons::jde($year, Season::MarchEquinox), self::DAY, (string) $year);
            self::assertEqualsWithDelta($june, Seasons::jde($year, Season::JuneSolstice), self::DAY, (string) $year);
            self::assertEqualsWithDelta($september, Seasons::jde($year, Season::SeptemberEquinox), self::DAY, (string) $year);
            self::assertEqualsWithDelta($december, Seasons::jde($year, Season::DecemberSolstice), self::DAY, (string) $year);
        }
    }

    /**
     * ΔT at the edges of each polynomial, where the next one would give another value.
     */
    public function testDeltaT(): void
    {
        foreach ([
            [1900.0, -2.790000000000], [1919.5, 21.033437137500], [1920.0, 21.200000000000], [1940.999, 24.771841092995],
            [1941.0, 24.773141433749], [1960.5, 33.324829334688], [1961.0, 33.579880865652], [1985.5, 54.617170452111],
            [1986.0, 54.877737538240], [2004.5, 64.611178993172], [2005.0, 64.670575000000], [2049.5, 92.561862250000],
            [2050.0, 93.000000000000], [2149.5, 327.143400000000], [2150.0, 328.480000000000], [1700.0, 26.080000000000],
            [2300.0, 717.280000000000], [1899.999, 0.479488003200],
        ] as [$year, $seconds]) {
            self::assertEqualsWithDelta($seconds, JulianDay::deltaT($year), 1.0E-9, (string) $year);
        }
    }

    public function testTheSun(): void
    {
        foreach ([[2448908.5, 199.908941860353], [2451545.0, 280.372554878810], [2460000.25, 335.859993689293], [2300000.5, 312.168484226364], [2700000.75, 12.064459953777]] as [$jde, $longitude]) {
            self::assertEqualsWithDelta($longitude, Sun::longitude($jde), 1.0E-9, (string) $jde);
        }
    }

    /**
     * An instant to the nearest second, and ΔT added back from the year the Julian day falls in.
     */
    public function testTheConversions(): void
    {
        self::assertSame('2000-01-01 11:58:57', JulianDay::toUtc(2451545.0 + 0.5 / 86400)->format('Y-m-d H:i:s'), 'rounded, not cut');
        self::assertSame('2000-01-01 11:58:56', JulianDay::toUtc(2451545.0 + 0.2 / 86400)->format('Y-m-d H:i:s'));

        $at = new DateTimeImmutable('2026-12-31 23:00:00 UTC');
        $julian = $at->getTimestamp() / 86400 + 2440587.5;
        self::assertEqualsWithDelta(JulianDay::deltaT(2000 + ($julian - 2451545.0) / 365.25), (JulianDay::fromUtc($at) - $julian) * 86400, 1.0E-4);
        self::assertEqualsWithDelta(75.6925, (JulianDay::fromUtc($at) - $julian) * 86400, 0.0001);
    }
}
