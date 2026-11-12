<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Julian days, and the difference between the dynamical time of the ephemerides and universal time.
 *
 * The astronomical algorithms give their instants as Julian Ephemeris Days, in Terrestrial (dynamical) Time; a clock
 * reads Universal Time, ΔT seconds behind — about 69 seconds in 2026.
 *
 * @internal
 */
final class JulianDay
{
    private const float UNIX_EPOCH = 2440587.5;

    /**
     * The instant of a Julian Ephemeris Day, in UTC, to the second.
     */
    public static function toUtc(float $jde): DateTimeImmutable
    {
        $seconds = ($jde - self::UNIX_EPOCH) * 86400 - self::deltaT(self::year($jde));

        return new DateTimeImmutable(\sprintf('@%.0F', $seconds))->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * The Julian Ephemeris Day of an instant.
     */
    public static function fromUtc(DateTimeInterface $at): float
    {
        $julian = $at->getTimestamp() / 86400 + self::UNIX_EPOCH;

        return $julian + self::deltaT(self::year($julian)) / 86400;
    }

    /**
     * ΔT = TT − UT, in seconds, for a decimal year: the polynomials of Espenak and Meeus from 1900 to 2150, and
     * their long-term parabola outside.
     */
    public static function deltaT(float $year): float
    {
        return match (true) {
            $year < 1900 => -20 + 32 * (($year - 1820) / 100) ** 2,
            $year < 1920 => self::poly($year - 1900, [-2.79, 1.494119, -0.0598939, 0.0061966, -0.000197]),
            $year < 1941 => self::poly($year - 1920, [21.20, 0.84493, -0.076100, 0.0020936]),
            $year < 1961 => self::poly($year - 1950, [29.07, 0.407, -1 / 233, 1 / 2547]),
            $year < 1986 => self::poly($year - 1975, [45.45, 1.067, -1 / 260, -1 / 718]),
            $year < 2005 => self::poly($year - 2000, [63.86, 0.3345, -0.060374, 0.0017275, 0.000651814, 0.00002373599]),
            $year < 2050 => self::poly($year - 2000, [62.92, 0.32217, 0.005589]),
            // Joined to the long-term parabola in 2150.
            default => -20 + 32 * (($year - 1820) / 100) ** 2 - 0.5628 * max(0, 2150 - $year),
        };
    }

    /**
     * The decimal year of a Julian day, as ΔT is tabulated.
     */
    private static function year(float $julian): float
    {
        return 2000 + ($julian - 2451545.0) / 365.25;
    }

    /**
     * @param list<float> $coefficients from the constant term
     */
    public static function poly(float $x, array $coefficients): float
    {
        $sum = 0.0;

        foreach (array_reverse($coefficients) as $coefficient) {
            $sum = $sum * $x + $coefficient;
        }

        return $sum;
    }
}
