<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

use DateTimeImmutable;
use Phil\Almanac\Exception\OutOfRange;

/**
 * Equinoxes and solstices, by the method of Jean Meeus, *Astronomical Algorithms* (2nd ed.), chapter 27: the mean
 * instant of table 27.B, valid from 1000 to 3000, corrected by the 24 periodic terms of table 27.C. The result is
 * within a minute of the exact instant over the twenty-first century.
 */
final class Seasons
{
    public const int FIRST_YEAR = 1000;

    public const int LAST_YEAR = 3000;

    /** Table 27.B: JDE0 = a + bY + cY² + dY³ + eY⁴, Y = (year − 2000) / 1000. */
    private const array MEAN = [
        'march equinox' => [2451623.80984, 365242.37404, 0.05169, -0.00411, -0.00057],
        'june solstice' => [2451716.56767, 365241.62603, 0.00325, 0.00888, -0.00030],
        'september equinox' => [2451810.21715, 365242.01767, -0.11575, 0.00337, 0.00078],
        'december solstice' => [2451900.05952, 365242.74049, -0.06223, -0.00823, 0.00032],
    ];

    /** Table 27.C: A, B (degrees), C (degrees per Julian century). */
    private const array TERMS = [
        [485, 324.96, 1934.136], [203, 337.23, 32964.467], [199, 342.08, 20.186], [182, 27.85, 445267.112],
        [156, 73.14, 45036.886], [136, 171.52, 22518.443], [77, 222.54, 65928.934], [74, 296.72, 3034.906],
        [70, 243.58, 9037.513], [58, 119.81, 33718.147], [52, 297.17, 150.678], [50, 21.02, 2281.226],
        [45, 247.54, 29929.562], [44, 325.15, 31555.956], [29, 60.93, 4443.417], [18, 155.12, 67555.328],
        [17, 288.79, 4562.452], [16, 198.04, 62894.029], [14, 199.76, 31436.921], [12, 95.39, 14577.848],
        [12, 287.11, 31931.756], [12, 320.81, 34777.259], [9, 227.73, 1222.114], [8, 15.45, 16859.074],
    ];

    /**
     * The instant of a season's start in a year, in UTC.
     */
    public static function of(int $year, Season $season): DateTimeImmutable
    {
        return JulianDay::toUtc(self::jde($year, $season));
    }

    /**
     * The Julian Ephemeris Day of a season's start.
     */
    public static function jde(int $year, Season $season): float
    {
        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            throw OutOfRange::year($year, 'A season', self::FIRST_YEAR, self::LAST_YEAR);
        }

        $mean = JulianDay::poly(($year - 2000) / 1000, self::MEAN[$season->value]);
        $t = ($mean - 2451545.0) / 36525;
        $w = deg2rad(35999.373 * $t - 2.47);
        $lambda = 1 + 0.0334 * cos($w) + 0.0007 * cos(2 * $w);
        $sum = 0.0;

        foreach (self::TERMS as [$a, $b, $c]) {
            $sum += $a * cos(deg2rad($b + $c * $t));
        }

        return $mean + 0.00001 * $sum / $lambda;
    }
}
