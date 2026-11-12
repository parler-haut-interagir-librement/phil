<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\Almanac\Exception\OutOfRange;

/**
 * Solar and lunar eclipses, by the method of Jean Meeus, *Astronomical Algorithms* (2nd ed.), chapter 54: each new
 * and full moon whose argument of latitude is near a node is corrected to the instant of the greatest eclipse, then
 * its `gamma` and the radius of the shadow tell whether there is an eclipse and of which kind. The instants are within
 * a few minutes of the exact ones, the magnitudes within a few hundredths; they are given from 1000 to 3000.
 */
final class Eclipses
{
    public const int FIRST_YEAR = 1000;

    public const int LAST_YEAR = 3000;

    private const float SYNODIC_MONTH = 29.530588861;

    /** The first and last new moons of the years covered, from that of 2000 January 6. */
    private const float FIRST_LUNATION = -12368.0;

    private const float LAST_LUNATION = 12380.0;

    /** Further from a node than this, `|sin F|`, a syzygy has no eclipse. */
    private const float NEAR_A_NODE = 0.36;

    /**
     * The solar eclipses whose greatest falls between two moments, the first included and the second not, in order.
     *
     * @return list<SolarEclipse>
     */
    public static function solar(DateTimeInterface $from, DateTimeInterface $until): array
    {
        $found = [];

        foreach (self::lunations($from, $until) as $k) {
            $eclipse = self::solarAt($k);

            if ($eclipse instanceof SolarEclipse && $eclipse->at >= $from && $eclipse->at < $until) {
                $found[] = $eclipse;
            }
        }

        return $found;
    }

    /**
     * The lunar eclipses whose greatest falls between two moments, the first included and the second not, in order.
     *
     * @return list<LunarEclipse>
     */
    public static function lunar(DateTimeInterface $from, DateTimeInterface $until): array
    {
        $found = [];

        foreach (self::lunations($from, $until) as $k) {
            $eclipse = self::lunarAt($k + 0.5);

            if ($eclipse instanceof LunarEclipse && $eclipse->at >= $from && $eclipse->at < $until) {
                $found[] = $eclipse;
            }
        }

        return $found;
    }

    /**
     * The solar eclipse of the new moon `k` lunations after that of 2000 January 6, if there is one.
     */
    public static function solarAt(float $k): ?SolarEclipse
    {
        $syzygy = self::syzygy($k, true);

        if (null === $syzygy) {
            return null;
        }

        [$jde, $gamma, $u] = $syzygy;
        $distance = abs($gamma);

        if ($distance > 1.5433 + $u) {
            return null;
        }

        $at = JulianDay::toUtc($jde);

        if ($distance < 0.9972) {
            return new SolarEclipse($at, self::centralKind($gamma, $u), true, $gamma, null);
        }

        // Neither central nor partial: the edge of the shadow alone touches the Earth, near a pole.
        if ($distance < 0.9972 + abs($u)) {
            return new SolarEclipse($at, $u < 0 ? SolarEclipseKind::Total : SolarEclipseKind::Annular, false, $gamma, null);
        }

        return new SolarEclipse($at, SolarEclipseKind::Partial, false, $gamma, (1.5433 + $u - $distance) / (0.5461 + 2 * $u));
    }

    /**
     * The lunar eclipse of the full moon `k` lunations after the new moon of 2000 January 6 — a whole number and a
     * half —, if there is one.
     */
    public static function lunarAt(float $k): ?LunarEclipse
    {
        $syzygy = self::syzygy($k, false);

        if (null === $syzygy) {
            return null;
        }

        [$jde, $gamma, $u, $meanAnomaly] = $syzygy;
        $distance = abs($gamma);
        $penumbral = (1.5573 + $u - $distance) / 0.5450;

        if ($penumbral <= 0) {
            return null;
        }

        $umbral = (1.0128 - $u - $distance) / 0.5450;
        // The Moon's hourly motion against the shadow, in Earth radii.
        $motion = 0.5458 + 0.0400 * cos($meanAnomaly);
        $at = JulianDay::toUtc($jde);
        $span = static fn (float $radius): array => [
            self::moved($at, -60 / $motion * sqrt($radius * $radius - $gamma * $gamma)),
            self::moved($at, 60 / $motion * sqrt($radius * $radius - $gamma * $gamma)),
        ];

        return new LunarEclipse(
            $at,
            match (true) {
                $umbral >= 1 => LunarEclipseKind::Total,
                $umbral > 0 => LunarEclipseKind::Partial,
                default => LunarEclipseKind::Penumbral,
            },
            $gamma,
            $penumbral,
            $umbral,
            $span(1.5573 + $u),
            $umbral > 0 ? $span(1.0128 - $u) : null,
            $umbral >= 1 ? $span(0.4678 - $u) : null,
        );
    }

    /**
     * The kind of a central solar eclipse, by the radius `u` of the umbra on the fundamental plane: negative for a
     * total eclipse; for a small positive one, total along the middle of its path and annular at its ends.
     */
    private static function centralKind(float $gamma, float $u): SolarEclipseKind
    {
        if ($u < 0) {
            return SolarEclipseKind::Total;
        }

        if ($u > 0.0047) {
            return SolarEclipseKind::Annular;
        }

        return $u < 0.00464 * sqrt(1 - $gamma * $gamma) ? SolarEclipseKind::Hybrid : SolarEclipseKind::Annular;
    }

    /**
     * The instant of the greatest eclipse, its `gamma`, the radius `u` of the umbra and the Moon's mean anomaly, of a
     * syzygy near a node; null for one too far from it.
     *
     * @return array{float, float, float, float}|null
     */
    private static function syzygy(float $k, bool $new): ?array
    {
        $year = (int) floor(2000 + $k / 12.3685);

        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            throw OutOfRange::year($year, 'An eclipse', self::FIRST_YEAR, self::LAST_YEAR);
        }

        $t = $k / 1236.85;
        $f = deg2rad(JulianDay::poly($t, [160.7108 + 390.67050284 * $k, 0, -0.0016118, -0.00000227, 0.000000011]));

        if (abs(sin($f)) > self::NEAR_A_NODE) {
            return null;
        }

        $m = deg2rad(JulianDay::poly($t, [2.5534 + 29.10535670 * $k, 0, -0.0000014, -0.00000011]));
        $mPrime = deg2rad(JulianDay::poly($t, [201.5643 + 385.81693528 * $k, 0, 0.0107582, 0.00001238, -0.000000058]));
        $omega = deg2rad(JulianDay::poly($t, [124.7746 - 1.56375588 * $k, 0, 0.0020672, 0.00000215]));
        $e = JulianDay::poly($t, [1, -0.002516, -0.0000074]);
        $f1 = $f - deg2rad(0.02665) * sin($omega);
        $a1 = deg2rad(299.77 + 0.107408 * $k - 0.009173 * $t * $t);

        $jde = JulianDay::poly($t, [2451550.09766 + self::SYNODIC_MONTH * $k, 0, 0.00015437, -0.000000150, 0.00000000073])
            + ($new ? -0.4075 : -0.4065) * sin($mPrime)
            + ($new ? 0.1721 : 0.1727) * $e * sin($m)
            + 0.0161 * sin(2 * $mPrime)
            - 0.0097 * sin(2 * $f1)
            + 0.0073 * $e * sin($mPrime - $m)
            - 0.0050 * $e * sin($mPrime + $m)
            - 0.0023 * sin($mPrime - 2 * $f1)
            + 0.0021 * $e * sin(2 * $m)
            + 0.0012 * sin($mPrime + 2 * $f1)
            + 0.0006 * $e * sin(2 * $mPrime + $m)
            - 0.0004 * sin(3 * $mPrime)
            - 0.0003 * $e * sin($m + 2 * $f1)
            + 0.0003 * sin($a1)
            - 0.0002 * $e * sin($m - 2 * $f1)
            - 0.0002 * $e * sin(2 * $mPrime - $m)
            - 0.0002 * sin($omega);

        $p = 0.2070 * $e * sin($m) + 0.0024 * $e * sin(2 * $m) - 0.0392 * sin($mPrime) + 0.0116 * sin(2 * $mPrime)
            - 0.0073 * $e * sin($mPrime + $m) + 0.0067 * $e * sin($mPrime - $m) + 0.0118 * sin(2 * $f1);
        $q = 5.2207 - 0.0048 * $e * cos($m) + 0.0020 * $e * cos(2 * $m) - 0.3299 * cos($mPrime)
            - 0.0060 * $e * cos($mPrime + $m) + 0.0041 * $e * cos($mPrime - $m);
        $gamma = ($p * cos($f1) + $q * sin($f1)) * (1 - 0.0048 * abs(cos($f1)));
        $u = 0.0059 + 0.0046 * $e * cos($m) - 0.0182 * cos($mPrime) + 0.0004 * cos(2 * $mPrime) - 0.0005 * cos($m + $mPrime);

        return [$jde, $gamma, $u, $mPrime];
    }

    /**
     * The new moons, by their `k`, whose eclipses can fall between two moments: the greatest of an eclipse is within
     * a day of its mean syzygy, and the lunations walked stay in the years the method covers.
     *
     * @return list<float>
     */
    private static function lunations(DateTimeInterface $from, DateTimeInterface $until): array
    {
        foreach ([$from, $until] as $bound) {
            $year = (int) DateTimeImmutable::createFromInterface($bound)->setTimezone(new DateTimeZone('UTC'))->format('Y');

            if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR + 1) {
                throw OutOfRange::year($year, 'An eclipse', self::FIRST_YEAR, self::LAST_YEAR);
            }
        }

        $first = max(self::FIRST_LUNATION, floor((JulianDay::fromUtc($from) - 2451550.09766) / self::SYNODIC_MONTH));
        $last = min(self::LAST_LUNATION, ceil((JulianDay::fromUtc($until) - 2451550.09766) / self::SYNODIC_MONTH));

        return $first > $last ? [] : range($first, $last);
    }

    private static function moved(DateTimeImmutable $at, float $minutes): DateTimeImmutable
    {
        return $at->modify(\sprintf('%+d seconds', (int) round($minutes * 60)));
    }
}
