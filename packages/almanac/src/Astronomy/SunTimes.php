<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\Almanac\Exception\OutOfRange;
use Phil\Almanac\Place;

/**
 * Sunrise, sunset, the solar noon and the three twilights of a place on a day.
 *
 * The solar noon is the transit of the Sun, mean noon at the place's longitude corrected by the equation of time.
 * Each crossing starts from it: the hour angle at which the Sun's centre reaches the altitude, for the Sun's
 * declination at that moment, gives a first instant, where declination and equation of time are read again, four
 * times. Sunrise and sunset take the Sun's upper limb on a sea horizon, 0.8333° below it: 34′ of refraction and 16′
 * of the Sun's half-diameter. The times are within a minute of the published ones between the polar circles; near
 * them, where the Sun grazes the horizon, a few minutes.
 */
final class SunTimes
{
    public const int FIRST_YEAR = 1000;

    public const int LAST_YEAR = 3000;

    /** The altitude of the Sun's centre at sunrise and sunset, in degrees. */
    public const float HORIZON = -0.8333;

    private const float UNIX_EPOCH = 2440587.5;

    private const int ITERATIONS = 4;

    /**
     * The solar day of a place on the date a moment falls on in the place's time zone.
     */
    public static function on(Place $place, DateTimeInterface $day): SolarDay
    {
        $local = DateTimeImmutable::createFromInterface($day)->setTimezone($place->zone);
        $year = (int) $local->format('Y');

        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            throw OutOfRange::year($year, 'The Sun of a day', self::FIRST_YEAR, self::LAST_YEAR);
        }

        // The Julian day of the date's midnight in universal time: the transit of a longitude falls within its day.
        $midnight = new DateTimeImmutable($local->format('Y-m-d'), new DateTimeZone('UTC'))->getTimestamp() / 86400 + self::UNIX_EPOCH;
        $noon = self::transit($midnight, $place->longitude);
        $twilights = [];

        foreach (Twilight::cases() as $twilight) {
            $twilights[$twilight->value] = self::crossing($place, $noon, -$twilight->depression());
        }

        return new SolarDay(
            $place,
            $local->setTime(0, 0),
            self::instant($noon, $place->zone),
            self::crossing($place, $noon, self::HORIZON),
            $twilights,
        );
    }

    /**
     * The Julian day, in universal time, of the Sun's transit on a day.
     */
    private static function transit(float $midnight, float $longitude): float
    {
        $at = $midnight + 0.5 - $longitude / 360;

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $at = $midnight + 0.5 - $longitude / 360 - Sun::equationOfTime(self::ephemeris($at)) / 1440;
        }

        return $at;
    }

    /**
     * When the Sun's centre crosses an altitude before and after a transit.
     */
    private static function crossing(Place $place, float $noon, float $altitude): Crossing
    {
        $instants = [];
        $noonEquation = Sun::equationOfTime(self::ephemeris($noon));

        foreach ([-1, 1] as $side) {
            $at = $noon;

            for ($i = 0; $i < self::ITERATIONS; ++$i) {
                $jde = self::ephemeris($at);
                $hourAngle = self::hourAngle($place->latitude, Sun::declination($jde), $altitude);

                if ($hourAngle instanceof Altitude) {
                    return Crossing::never($hourAngle);
                }

                $at = $noon + ($side * $hourAngle) / 360 + ($noonEquation - Sun::equationOfTime($jde)) / 1440;
            }

            $instants[] = self::instant($at, $place->zone);
        }

        return Crossing::at($instants[0], $instants[1]);
    }

    /**
     * The hour angle, in degrees, at which the Sun's centre is at an altitude; or where the Sun stays when it never is.
     */
    private static function hourAngle(float $latitude, float $declination, float $altitude): float|Altitude
    {
        $phi = deg2rad($latitude);
        $delta = deg2rad($declination);
        $cosine = (sin(deg2rad($altitude)) - sin($phi) * sin($delta)) / (cos($phi) * cos($delta));

        return match (true) {
            $cosine > 1 => Altitude::AlwaysBelow,
            $cosine < -1 => Altitude::AlwaysAbove,
            default => rad2deg(acos($cosine)),
        };
    }

    /**
     * The Julian Ephemeris Day of a Julian day in universal time.
     */
    private static function ephemeris(float $julian): float
    {
        return $julian + JulianDay::deltaT(2000 + ($julian - 2451545.0) / 365.25) / 86400;
    }

    private static function instant(float $julian, DateTimeZone $zone): DateTimeImmutable
    {
        return new DateTimeImmutable(\sprintf('@%.0F', round(($julian - self::UNIX_EPOCH) * 86400)))->setTimezone($zone);
    }
}
