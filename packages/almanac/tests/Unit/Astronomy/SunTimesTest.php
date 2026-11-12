<?php

declare(strict_types=1);

namespace Phil\Almanac\Tests\Unit\Astronomy;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Astronomy\Altitude;
use Phil\Almanac\Astronomy\Crossing;
use Phil\Almanac\Astronomy\JulianDay;
use Phil\Almanac\Astronomy\SolarDay;
use Phil\Almanac\Astronomy\Sun;
use Phil\Almanac\Astronomy\SunTimes;
use Phil\Almanac\Astronomy\Twilight;
use Phil\Almanac\Exception\InvalidPlace;
use Phil\Almanac\Exception\OutOfRange;
use Phil\Almanac\Place;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SunTimes::class)]
#[CoversClass(SolarDay::class)]
#[CoversClass(Crossing::class)]
#[CoversClass(Altitude::class)]
#[CoversClass(Twilight::class)]
#[CoversClass(Sun::class)]
#[CoversClass(Place::class)]
#[CoversClass(InvalidPlace::class)]
#[CoversClass(JulianDay::class)]
#[CoversClass(OutOfRange::class)]
final class SunTimesTest extends TestCase
{
    /**
     * Meeus, example 25.a: the Sun on 1992 October 13.0 TD — apparent longitude 199.90895°, right ascension
     * 198.38083°, declination −7.78507° —, and example 28.b: the equation of time that day, 13 min 42.7 s.
     */
    public function testTheExamplesOfMeeus(): void
    {
        $jde = 2448908.5;

        self::assertEqualsWithDelta(199.90895, Sun::longitude($jde), 0.0001);
        self::assertEqualsWithDelta(198.38083, Sun::rightAscension($jde), 0.0001);
        self::assertEqualsWithDelta(-7.78507, Sun::declination($jde), 0.0001);
        self::assertEqualsWithDelta(13 + 42.7 / 60, Sun::equationOfTime($jde), 0.01);
        // In February the Sun is behind the mean Sun, and its right ascension past 270°: both stay in their range.
        self::assertEqualsWithDelta(-14.2, Sun::equationOfTime(JulianDay::fromUtc(new DateTimeImmutable('2026-02-11 12:00 UTC'))), 0.1);
        self::assertGreaterThan(300, Sun::rightAscension(JulianDay::fromUtc(new DateTimeImmutable('2026-02-11 12:00 UTC'))));
    }

    /**
     * Sunrise and sunset as published sunrise tables give them, to the minute, in local time.
     *
     * @return iterable<string, array{float, float, string, string, string, string}>
     */
    public static function publishedTimes(): iterable
    {
        yield 'Montréal, June 20' => [45.5017, -73.5673, 'America/Toronto', '2026-06-20', '2026-06-20 05:05', '2026-06-20 20:47'];
        yield 'New York, June 21' => [40.7128, -74.0060, 'America/New_York', '2026-06-21', '2026-06-21 05:25', '2026-06-21 20:31'];
        yield 'New York, December 21' => [40.7128, -74.0060, 'America/New_York', '2026-12-21', '2026-12-21 07:17', '2026-12-21 16:32'];
        yield 'London, June 21' => [51.5074, -0.1278, 'Europe/London', '2026-06-21', '2026-06-21 04:43', '2026-06-21 21:21'];
        yield 'London, December 21' => [51.5074, -0.1278, 'Europe/London', '2026-12-21', '2026-12-21 08:04', '2026-12-21 15:53'];
        yield 'Sydney, June 21' => [-33.8688, 151.2093, 'Australia/Sydney', '2026-06-21', '2026-06-21 07:00', '2026-06-21 16:54'];
        yield 'Sydney, December 21' => [-33.8688, 151.2093, 'Australia/Sydney', '2026-12-21', '2026-12-21 05:41', '2026-12-21 20:05'];
        // The sunset of the solar day falls on the next date.
        yield 'Reykjavík, June 21' => [64.1466, -21.9426, 'Atlantic/Reykjavik', '2026-06-21', '2026-06-21 02:55', '2026-06-22 00:04'];
    }

    #[DataProvider('publishedTimes')]
    public function testThePublishedTimes(float $latitude, float $longitude, string $zone, string $date, string $sunrise, string $sunset): void
    {
        $timezone = new DateTimeZone($zone);
        $day = SunTimes::on(new Place($latitude, $longitude, $timezone), new DateTimeImmutable($date.' 15:00', $timezone));

        self::assertSame($date.' 00:00', $day->date->format('Y-m-d H:i'));
        self::assertSame($zone, $day->sunrise()?->getTimezone()->getName());
        self::assertSame(Altitude::Crosses, $day->sun->sun);
        $rise = $day->sunrise();
        $set = $day->sunset();
        self::assertNotNull($rise);
        self::assertNotNull($set);
        $this->assertNear($sunrise, $rise, $timezone);
        $this->assertNear($sunset, $set, $timezone);
        self::assertSame($set->getTimestamp() - $rise->getTimestamp(), $day->dayLength());
        self::assertLessThan($day->noon, $rise);
        self::assertGreaterThan($day->noon, $set);
    }

    /**
     * The twilights of Montréal at the June solstice, and of London, where the Sun never goes 18° below the horizon
     * in June: astronomical twilight lasts all night.
     */
    public function testTheTwilights(): void
    {
        $zone = new DateTimeZone('America/Toronto');
        $day = SunTimes::on(new Place(45.5017, -73.5673, $zone), new DateTimeImmutable('2026-06-20', $zone));

        $this->assertNear('2026-06-20 04:27', $day->dawn(), $zone);
        $this->assertNear('2026-06-20 21:25', $day->dusk(), $zone);
        $this->assertNear('2026-06-20 03:36', $day->dawn(Twilight::Nautical), $zone);
        $this->assertNear('2026-06-20 02:27', $day->dawn(Twilight::Astronomical), $zone);
        $this->assertNear('2026-06-20 12:56', $day->noon, $zone);
        self::assertLessThan($day->dawn(), $day->dawn(Twilight::Nautical));
        self::assertGreaterThan($day->dusk(Twilight::Nautical), $day->dusk(Twilight::Astronomical));
        self::assertSame($day->twilight(Twilight::Civil)->evening, $day->dusk(Twilight::Civil));

        $london = new DateTimeZone('Europe/London');
        $june = SunTimes::on(new Place(51.5074, -0.1278, $london), new DateTimeImmutable('2026-06-21', $london));
        self::assertSame(Altitude::AlwaysAbove, $june->twilight(Twilight::Astronomical)->sun);
        self::assertNull($june->dawn(Twilight::Astronomical));
        self::assertNull($june->dusk(Twilight::Astronomical));
        self::assertSame(86400, $june->twilight(Twilight::Astronomical)->seconds());
        self::assertSame(Altitude::Crosses, $june->twilight(Twilight::Nautical)->sun);
        self::assertSame([6.0, 12.0, 18.0], array_map(static fn (Twilight $twilight): float => $twilight->depression(), Twilight::cases()));
    }

    public function testTheMidnightSunAndThePolarNight(): void
    {
        $zone = new DateTimeZone('Europe/Oslo');
        $tromso = new Place(69.6492, 18.9553, $zone);

        $summer = SunTimes::on($tromso, new DateTimeImmutable('2026-06-21', $zone));
        self::assertSame(Altitude::AlwaysAbove, $summer->sun->sun);
        self::assertNull($summer->sunrise());
        self::assertNull($summer->sunset());
        self::assertSame(86400, $summer->dayLength());

        $winter = SunTimes::on($tromso, new DateTimeImmutable('2026-12-21', $zone));
        self::assertSame(Altitude::AlwaysBelow, $winter->sun->sun);
        self::assertSame(0, $winter->dayLength());
        // The Sun still comes within 6° of the horizon at noon: there is a civil twilight.
        self::assertSame(Altitude::Crosses, $winter->twilight(Twilight::Civil)->sun);
        self::assertLessThan($winter->noon, $winter->dawn());

        $pole = SunTimes::on(new Place(-90, 0, new DateTimeZone('UTC')), new DateTimeImmutable('2026-12-21'));
        self::assertSame(Altitude::AlwaysAbove, $pole->sun->sun);
        self::assertSame(Altitude::AlwaysAbove, $pole->twilight(Twilight::Astronomical)->sun);
    }

    /**
     * PHP's `date_sun_info()`, an algorithm of its own, agrees within a minute and a half over a year, away from the
     * days where the Sun grazes an altitude.
     */
    public function testAnotherAlgorithmAgrees(): void
    {
        $keys = ['sunrise', 'sunset', 'transit', 'civil_twilight_begin', 'civil_twilight_end', 'nautical_twilight_begin', 'nautical_twilight_end'];

        foreach ([[45.5017, -73.5673, 'America/Toronto'], [-33.8688, 151.2093, 'Australia/Sydney'], [0.0, -78.5, 'America/Guayaquil']] as [$latitude, $longitude, $zone]) {
            $timezone = new DateTimeZone($zone);
            $place = new Place($latitude, $longitude, $timezone);

            for ($days = 0; $days < 365; $days += 13) {
                $at = new DateTimeImmutable('2026-01-01 12:00', $timezone)->modify(\sprintf('+%d days', $days));
                $day = SunTimes::on($place, $at);
                $reference = date_sun_info($at->getTimestamp(), $latitude, $longitude);
                $ours = [$day->sunrise(), $day->sunset(), $day->noon, $day->dawn(), $day->dusk(), $day->dawn(Twilight::Nautical), $day->dusk(Twilight::Nautical)];

                foreach ($keys as $index => $key) {
                    self::assertIsInt($reference[$key]);
                    self::assertEqualsWithDelta($reference[$key], $ours[$index]?->getTimestamp(), 90, \sprintf('%s, %s, %s', $zone, $at->format('Y-m-d'), $key));
                }
            }
        }
    }

    public function testTheDayOfAMomentIsReadInThePlacesTimeZone(): void
    {
        $zone = new DateTimeZone('America/Toronto');
        $place = new Place(45.5017, -73.5673, $zone);

        // 02:00 UTC on June 21 is still June 20 in Montréal.
        self::assertSame('2026-06-20', SunTimes::on($place, new DateTimeImmutable('2026-06-21 02:00 UTC'))->date->format('Y-m-d'));
        self::assertSame($place, SunTimes::on($place, new DateTimeImmutable('2026-06-21 02:00 UTC'))->place);
    }

    public function testTheRangeOfYearsAndOfCoordinates(): void
    {
        $utc = new DateTimeZone('UTC');
        $place = new Place(0, 0, $utc);

        self::assertSame('1000-01-01', SunTimes::on($place, new DateTimeImmutable('1000-01-01', $utc))->date->format('Y-m-d'));
        self::assertSame('3000-12-31', SunTimes::on($place, new DateTimeImmutable('3000-12-31', $utc))->date->format('Y-m-d'));

        foreach (['0999-12-31', '3001-01-01'] as $date) {
            try {
                SunTimes::on($place, new DateTimeImmutable($date, $utc));
                self::fail($date.' is out of range.');
            } catch (OutOfRange $outOfRange) {
                self::assertStringContainsString('The Sun of a day is computed for the years 1000 to 3000', $outOfRange->getMessage());
            }
        }

        foreach ([[90.0, 180.0], [-90.0, -180.0]] as [$latitude, $longitude]) {
            self::assertSame($latitude, new Place($latitude, $longitude, $utc)->latitude);
        }

        foreach ([[90.001, 0.0, 'A latitude is between -90 and 90 degrees, not 90.001.'], [-90.001, 0.0, 'latitude'], [0.0, 180.001, 'A longitude is between -180 and 180 degrees, not 180.001.'], [0.0, -180.001, 'longitude'], [\NAN, 0.0, 'latitude'], [0.0, \INF, 'longitude']] as [$latitude, $longitude, $message]) {
            try {
                new Place($latitude, $longitude, $utc);
                self::fail('Out of range.');
            } catch (InvalidPlace $invalidPlace) {
                self::assertStringContainsString($message, $invalidPlace->getMessage());
            }
        }
    }

    private function assertNear(string $expected, ?DateTimeImmutable $actual, DateTimeZone $zone): void
    {
        self::assertNotNull($actual);
        self::assertEqualsWithDelta(new DateTimeImmutable($expected, $zone)->getTimestamp() + 30, $actual->getTimestamp(), 60, \sprintf('%s, not %s', $expected, $actual->format('Y-m-d H:i:s')));
    }
}
