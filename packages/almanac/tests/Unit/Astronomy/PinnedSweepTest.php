<?php

declare(strict_types=1);

namespace Phil\Almanac\Tests\Unit\Astronomy;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\Almanac\Astronomy\Altitude;
use Phil\Almanac\Astronomy\Crossing;
use Phil\Almanac\Astronomy\Eclipses;
use Phil\Almanac\Astronomy\JulianDay;
use Phil\Almanac\Astronomy\LunarEclipse;
use Phil\Almanac\Astronomy\LunarEclipseKind;
use Phil\Almanac\Astronomy\SolarDay;
use Phil\Almanac\Astronomy\SolarEclipse;
use Phil\Almanac\Astronomy\SolarEclipseKind;
use Phil\Almanac\Astronomy\Sun;
use Phil\Almanac\Astronomy\SunTimes;
use Phil\Almanac\Astronomy\Twilight;
use Phil\Almanac\Exception\OutOfRange;
use Phil\Almanac\Place;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The Sun and the eclipses over their whole range, each value to the second or to nine decimals, summed in one
 * digest: a change to any term of the algorithms, in any year, shows. The digests are those of the computations
 * checked against Meeus's examples and the published tables in the other tests; a deliberate change to an algorithm
 * moves them, and they are written again then.
 */
#[CoversClass(Eclipses::class)]
#[CoversClass(SolarEclipse::class)]
#[CoversClass(LunarEclipse::class)]
#[CoversClass(SolarEclipseKind::class)]
#[CoversClass(LunarEclipseKind::class)]
#[CoversClass(SunTimes::class)]
#[CoversClass(SolarDay::class)]
#[CoversClass(Crossing::class)]
#[CoversClass(Sun::class)]
#[CoversClass(JulianDay::class)]
#[CoversClass(Place::class)]
#[CoversClass(OutOfRange::class)]
final class PinnedSweepTest extends TestCase
{
    public function testTheSolarEclipsesOfTheWholeRange(): void
    {
        $digest = '';
        $count = 0;

        for ($k = -12368; $k <= 12380; ++$k) {
            $eclipse = Eclipses::solarAt((float) $k);

            if ($eclipse instanceof SolarEclipse) {
                ++$count;
                $digest .= \sprintf("%d %d %s %d %.9F %s\n", $k, $eclipse->at->getTimestamp(), $eclipse->kind->value, (int) $eclipse->central, $eclipse->gamma, null === $eclipse->magnitude ? '-' : \sprintf('%.9F', $eclipse->magnitude));
            }
        }

        self::assertSame(4774, $count);
        self::assertSame('8c68dc96a6d1a1a910a4cd11bb4772eeea4e9bdc837e83560389f5570b65649a', hash('sha256', $digest));
    }

    public function testTheLunarEclipsesOfTheWholeRange(): void
    {
        $digest = '';
        $count = 0;
        $instants = static fn (?array $span): string => null === $span ? '-' : $span[0]->getTimestamp().'/'.$span[1]->getTimestamp();

        for ($k = -12368; $k <= 12380; ++$k) {
            $eclipse = Eclipses::lunarAt($k + 0.5);

            if ($eclipse instanceof LunarEclipse) {
                ++$count;
                $digest .= \sprintf("%d %d %s %.9F %.9F %.9F %s %s %s\n", $k, $eclipse->at->getTimestamp(), $eclipse->kind->value, $eclipse->gamma, $eclipse->penumbralMagnitude, $eclipse->umbralMagnitude, $instants($eclipse->penumbral), $instants($eclipse->partial), $instants($eclipse->total));
            }
        }

        self::assertSame(4837, $count);
        self::assertSame('89c8b6eaee7202ba5f95127918bd3853e5a599e3e5070d88ba7acb80aa4fb63e', hash('sha256', $digest));
    }

    /**
     * The eclipses of a period are those of its lunations, whichever moments bound it: from the first lunation to the
     * last of the range, and over a window of a week, a day, a few minutes.
     */
    public function testTheEclipsesOfPeriods(): void
    {
        $utc = new DateTimeZone('UTC');
        $digest = '';

        foreach ([['1000-01-01', '1002-01-01'], ['2999-01-01', '3001-01-01'], ['2026-08-10', '2026-08-18'], ['2026-08-12 17:00', '2026-08-12 18:00'], ['2026-08-28 04:00', '2026-08-28 04:30'], ['2026-03-03', '2026-03-04'], ['2026-03-03 11:00', '2026-03-03 12:00'], ['2024-01-01', '2024-01-01']] as [$from, $until]) {
            $from = new DateTimeImmutable($from, $utc);
            $until = new DateTimeImmutable($until, $utc);

            foreach ([...Eclipses::solar($from, $until), ...Eclipses::lunar($from, $until)] as $eclipse) {
                $digest .= $eclipse::class.' '.$eclipse->at->format(DateTimeInterface::ATOM)."\n";
            }

            $digest .= "--\n";
        }

        self::assertSame('ffa370bf9d9d0f9fb704c524d334e246bbb74cfd8d6aeb34d4b046206ab3d8fd', hash('sha256', $digest));
        self::assertCount(0, Eclipses::lunar(new DateTimeImmutable('2026-03-03 11:34:00', $utc), new DateTimeImmutable('2026-03-03 11:34:00', $utc)));
    }

    public function testTheLimitsOfTheRange(): void
    {
        foreach ([[-12369.0, 'solar'], [12381.0, 'solar'], [-12368.5 - 1, 'lunar'], [12380.5 + 1, 'lunar']] as [$k, $body]) {
            try {
                'solar' === $body ? Eclipses::solarAt($k) : Eclipses::lunarAt($k);
                self::fail('Out of range.');
            } catch (OutOfRange $outOfRange) {
                self::assertStringContainsString('An eclipse is computed for the years 1000 to 3000', $outOfRange->getMessage());
            }
        }

        // The period is checked at its first moment as at its last, and may end in the year after the last.
        foreach ([['0999-12-31', '2000-01-01'], ['2000-01-01', '3002-01-01']] as [$from, $until]) {
            try {
                Eclipses::solar(new DateTimeImmutable($from.' UTC'), new DateTimeImmutable($until.' UTC'));
                self::fail('Out of range.');
            } catch (OutOfRange $outOfRange) {
                self::assertStringContainsString('An eclipse is computed for the years 1000 to 3000', $outOfRange->getMessage());
            }
        }

        self::assertGreaterThan(0, \count(Eclipses::solar(new DateTimeImmutable('2999-01-01 UTC'), new DateTimeImmutable('3001-01-01 UTC'))));
        self::assertNull(Eclipses::solarAt(12380.0));
    }

    /**
     * Declination, right ascension and equation of time, to nine decimals, from the first century to the last.
     */
    public function testTheSunOverTheRange(): void
    {
        $digest = '';

        for ($jde = 2086302.5; $jde < 2816788.0; $jde += 10_000.37) {
            $digest .= \sprintf("%.3F %.9F %.9F %.9F %.9F\n", $jde, Sun::longitude($jde), Sun::declination($jde), Sun::rightAscension($jde), Sun::equationOfTime($jde));
        }

        self::assertSame('880f7b7bc66344789f28f0aaf91132f0dce81b85a2323a265abf3a8354cd36f5', hash('sha256', $digest));
    }

    /**
     * Every crossing of the Sun, to the second, in places of every latitude and in years from 1000 to 3000.
     */
    public function testTheSolarDaysOfTheWholeRange(): void
    {
        $digest = '';
        $places = [
            [45.5017, -73.5673, 'America/Toronto'], [40.7128, -74.0060, 'America/New_York'], [51.5074, -0.1278, 'Europe/London'],
            [-33.8688, 151.2093, 'Australia/Sydney'], [64.1466, -21.9426, 'Atlantic/Reykjavik'], [69.6492, 18.9553, 'Europe/Oslo'],
            [0.0, 0.0, 'UTC'], [-54.8, -68.3, 'America/Argentina/Ushuaia'], [35.6762, 139.6503, 'Asia/Tokyo'], [78.2232, 15.6267, 'Arctic/Longyearbyen'],
            [-77.85, 166.67, 'Antarctica/McMurdo'], [21.3, -157.86, 'Pacific/Honolulu'], [66.5, 25.7, 'Europe/Helsinki'], [89.0, 90.0, 'UTC'], [-89.0, -120.0, 'UTC'],
        ];
        $text = static fn (?DateTimeImmutable $instant): string => $instant instanceof DateTimeImmutable ? (string) $instant->getTimestamp() : '-';

        foreach ($places as [$latitude, $longitude, $zone]) {
            $place = new Place($latitude, $longitude, new DateTimeZone($zone));

            foreach ([1000, 1492, 1900, 2026, 2400, 2999] as $year) {
                for ($day = 0; $day < 365; $day += 17) {
                    $date = new DateTimeImmutable(\sprintf('%04d-01-01 12:00', $year), $place->zone)->modify(\sprintf('+%d days', $day));
                    $solar = SunTimes::on($place, $date);
                    $digest .= \sprintf('%s %s %s', $text($solar->noon), $solar->sun->sun->name, $solar->dayLength());

                    foreach (Twilight::cases() as $twilight) {
                        $crossing = $solar->twilight($twilight);
                        $digest .= \sprintf(' %s %s %s', $crossing->sun->name, $text($crossing->morning), $text($crossing->evening));
                    }

                    $digest .= \sprintf(" %s %s\n", $text($solar->sunrise()), $text($solar->sunset()));
                }
            }
        }

        self::assertSame('b6ee57bad6267f83b07409abf03ebfd7001946cfca49e3a334b33f40bf62f9ab', hash('sha256', $digest));
        self::assertSame(Altitude::AlwaysAbove, SunTimes::on(new Place(89.0, 90.0, new DateTimeZone('UTC')), new DateTimeImmutable('2026-06-21 12:00 UTC'))->sun->sun);
    }
}
