<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

/**
 * The Sun's apparent position, by the low-accuracy method of Jean Meeus, *Astronomical Algorithms* (2nd ed.),
 * chapter 25 — within 0.01°, a quarter of an hour of the Sun's motion —, and the equation of time of chapter 28.
 */
final class Sun
{
    /**
     * The apparent geocentric longitude, in degrees from 0 to 360, at a Julian Ephemeris Day.
     */
    public static function longitude(float $jde): float
    {
        $t = self::centuries($jde);
        $anomaly = self::anomaly($t);
        $centre = JulianDay::poly($t, [1.914602, -0.004817, -0.000014]) * sin($anomaly)
            + (0.019993 - 0.000101 * $t) * sin(2 * $anomaly)
            + 0.000289 * sin(3 * $anomaly);

        return self::degrees(self::meanLongitude($t) + $centre - 0.00569 - 0.00478 * sin(self::node($t)));
    }

    /**
     * The apparent declination, in degrees north of the celestial equator, at a Julian Ephemeris Day.
     */
    public static function declination(float $jde): float
    {
        return rad2deg(asin(sin(self::obliquity(self::centuries($jde))) * sin(deg2rad(self::longitude($jde)))));
    }

    /**
     * The apparent right ascension, in degrees from 0 to 360, at a Julian Ephemeris Day.
     */
    public static function rightAscension(float $jde): float
    {
        $longitude = deg2rad(self::longitude($jde));

        return self::degrees(rad2deg(atan2(cos(self::obliquity(self::centuries($jde))) * sin($longitude), cos($longitude))));
    }

    /**
     * The equation of time, in minutes: apparent solar time less mean solar time, by the formula of Smart that Meeus
     * gives as 28.3 — within a few seconds.
     */
    public static function equationOfTime(float $jde): float
    {
        $t = self::centuries($jde);
        $y = tan(self::obliquity($t) / 2) ** 2;
        $mean = deg2rad(self::meanLongitude($t));
        $anomaly = self::anomaly($t);
        $e = JulianDay::poly($t, [0.016708634, -0.000042037, -0.0000001267]);

        $radians = $y * sin(2 * $mean)
            - 2 * $e * sin($anomaly)
            + 4 * $e * $y * sin($anomaly) * cos(2 * $mean)
            - $y * $y * sin(4 * $mean) / 2
            - 5 * $e * $e * sin(2 * $anomaly) / 4;

        return rad2deg($radians) * 4;
    }

    private static function centuries(float $jde): float
    {
        return ($jde - 2451545.0) / 36525;
    }

    private static function meanLongitude(float $t): float
    {
        return JulianDay::poly($t, [280.46646, 36000.76983, 0.0003032]);
    }

    private static function anomaly(float $t): float
    {
        return deg2rad(JulianDay::poly($t, [357.52911, 35999.05029, -0.0001537]));
    }

    private static function node(float $t): float
    {
        return deg2rad(125.04 - 1934.136 * $t);
    }

    /**
     * The apparent obliquity of the ecliptic, in radians: the mean obliquity of 22.2, corrected as chapter 25 does
     * for the Sun's apparent place.
     */
    private static function obliquity(float $t): float
    {
        $mean = 23 + (26 + JulianDay::poly($t, [21.448, -46.8150, -0.00059, 0.001813]) / 60) / 60;

        return deg2rad($mean + 0.00256 * cos(self::node($t)));
    }

    private static function degrees(float $angle): float
    {
        return fmod(fmod($angle, 360) + 360, 360);
    }
}
