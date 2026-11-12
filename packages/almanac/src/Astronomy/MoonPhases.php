<?php

declare(strict_types=1);

namespace Phil\Almanac\Astronomy;

use DateTimeInterface;
use Phil\Almanac\Exception\OutOfRange;

/**
 * The phases of the Moon, by the method of Jean Meeus, *Astronomical Algorithms* (2nd ed.), chapter 49: the mean
 * phase of a lunation, corrected by its periodic terms and the fourteen planetary arguments. The instants are within
 * a few seconds of the exact ones; they are given from 1000 to 3000.
 */
final class MoonPhases
{
    private const float SYNODIC_MONTH = 29.530588861;

    /** The coefficients of sin M', E sin M… for new and full moons: [new, full, the argument]. */
    private const array SYZYGY = [
        [-0.40720, -0.40614, [0, 1, 0, 0], 0], [0.17241, 0.17302, [1, 0, 0, 0], 1], [0.01608, 0.01614, [0, 2, 0, 0], 0],
        [0.01039, 0.01043, [0, 0, 2, 0], 0], [0.00739, 0.00734, [-1, 1, 0, 0], 1], [-0.00514, -0.00515, [1, 1, 0, 0], 1],
        [0.00208, 0.00209, [2, 0, 0, 0], 2], [-0.00111, -0.00111, [0, 1, -2, 0], 0], [-0.00057, -0.00057, [0, 1, 2, 0], 0],
        [0.00056, 0.00056, [1, 2, 0, 0], 1], [-0.00042, -0.00042, [0, 3, 0, 0], 0], [0.00042, 0.00042, [1, 0, 2, 0], 1],
        [0.00038, 0.00038, [1, 0, -2, 0], 1], [-0.00024, -0.00024, [-1, 2, 0, 0], 1], [-0.00017, -0.00017, [0, 0, 0, 1], 0],
        [-0.00007, -0.00007, [2, 1, 0, 0], 0], [0.00004, 0.00004, [0, 2, -2, 0], 0], [0.00004, 0.00004, [3, 0, 0, 0], 0],
        [0.00003, 0.00003, [1, 1, -2, 0], 0], [0.00003, 0.00003, [0, 2, 2, 0], 0], [-0.00003, -0.00003, [1, 1, 2, 0], 0],
        [0.00003, 0.00003, [-1, 1, 2, 0], 0], [-0.00002, -0.00002, [-1, 1, -2, 0], 0], [-0.00002, -0.00002, [1, 3, 0, 0], 0],
        [0.00002, 0.00002, [0, 4, 0, 0], 0],
    ];

    /** The coefficients for the quarters: [coefficient, [M, M', F, Ω], power of E]. */
    private const array QUARTER = [
        [-0.62801, [0, 1, 0, 0], 0], [0.17172, [1, 0, 0, 0], 1], [-0.01183, [1, 1, 0, 0], 1], [0.00862, [0, 2, 0, 0], 0],
        [0.00804, [0, 0, 2, 0], 0], [0.00454, [-1, 1, 0, 0], 1], [0.00204, [2, 0, 0, 0], 2], [-0.00180, [0, 1, -2, 0], 0],
        [-0.00070, [0, 1, 2, 0], 0], [-0.00040, [0, 3, 0, 0], 0], [-0.00034, [-1, 2, 0, 0], 1], [0.00032, [1, 0, 2, 0], 1],
        [0.00032, [1, 0, -2, 0], 1], [-0.00028, [2, 1, 0, 0], 2], [0.00027, [1, 2, 0, 0], 1], [-0.00017, [0, 0, 0, 1], 0],
        [-0.00005, [-1, 1, -2, 0], 0], [0.00004, [0, 2, 2, 0], 0], [-0.00004, [1, 1, 2, 0], 0], [0.00004, [-2, 1, 0, 0], 0],
        [0.00003, [1, 1, -2, 0], 0], [0.00003, [3, 0, 0, 0], 0], [0.00002, [0, 2, -2, 0], 0], [0.00002, [-1, 1, 2, 0], 0],
        [-0.00002, [1, 3, 0, 0], 0],
    ];

    /** The planetary arguments A1 to A14: [coefficient, constant, per lunation, per T²]. */
    private const array PLANETARY = [
        [0.000325, 299.77, 0.107408, -0.009173], [0.000165, 251.88, 0.016321, 0], [0.000164, 251.83, 26.651886, 0],
        [0.000126, 349.42, 36.412478, 0], [0.000110, 84.66, 18.206239, 0], [0.000062, 141.74, 53.303771, 0],
        [0.000060, 207.14, 2.453732, 0], [0.000056, 154.84, 7.306860, 0], [0.000047, 34.52, 27.261239, 0],
        [0.000042, 207.19, 0.121824, 0], [0.000040, 291.34, 1.844379, 0], [0.000037, 161.72, 24.198154, 0],
        [0.000035, 239.56, 25.513099, 0], [0.000023, 331.55, 3.592518, 0],
    ];

    /**
     * Every phase whose instant falls between two moments, the first included and the second not, in order: the
     * corrections never move a phase by a quarter of a lunation.
     *
     * @param list<Phase>|null $phases the phases wanted, all four by default
     *
     * @return list<MoonPhase>
     */
    public static function between(DateTimeInterface $from, DateTimeInterface $until, ?array $phases = null): array
    {
        $phases ??= Phase::cases();
        $found = [];
        // A phase's true instant is within a day of its mean one, which these lunations bound.
        $lunation = floor(self::lunations($from));
        $last = ceil(self::lunations($until));

        for (; $lunation <= $last; ++$lunation) {
            foreach ($phases as $phase) {
                $at = JulianDay::toUtc(self::jde($lunation + $phase->fraction(), $phase));

                if ($at >= $from && $at < $until) {
                    $found[] = new MoonPhase($phase, $at);
                }
            }
        }

        return $found;
    }

    /**
     * The Julian Ephemeris Day of a phase, `k` lunations after the new moon of 2000 January 6: an integer for a new
     * moon, plus a quarter for each phase after it.
     */
    public static function jde(float $k, Phase $phase): float
    {
        $year = (int) floor(2000 + $k / 12.3685);

        if ($year < 1000 || $year > 3000) {
            throw OutOfRange::year($year, 'A phase of the Moon', 1000, 3000);
        }

        $t = $k / 1236.85;

        $jde = JulianDay::poly($t, [2451550.09766 + self::SYNODIC_MONTH * $k, 0, 0.00015437, -0.000000150, 0.00000000073]);
        $e = JulianDay::poly($t, [1, -0.002516, -0.0000074]);
        $arguments = [
            deg2rad(JulianDay::poly($t, [2.5534 + 29.10535670 * $k, 0, -0.0000014, -0.00000011])),
            deg2rad(JulianDay::poly($t, [201.5643 + 385.81693528 * $k, 0, 0.0107582, 0.00001238, -0.000000058])),
            deg2rad(JulianDay::poly($t, [160.7108 + 390.67050284 * $k, 0, -0.0016118, -0.00000227, 0.000000011])),
            deg2rad(JulianDay::poly($t, [124.7746 - 1.56375588 * $k, 0, 0.0020672, 0.00000215])),
        ];

        $jde += match ($phase) {
            Phase::New => self::terms(array_map(static fn (array $term): array => [$term[0], $term[2], $term[3]], self::SYZYGY), $arguments, $e),
            Phase::Full => self::terms(array_map(static fn (array $term): array => [$term[1], $term[2], $term[3]], self::SYZYGY), $arguments, $e),
            Phase::FirstQuarter => self::terms(self::QUARTER, $arguments, $e) + self::quarter($arguments, $e),
            Phase::LastQuarter => self::terms(self::QUARTER, $arguments, $e) - self::quarter($arguments, $e),
        };

        foreach (self::PLANETARY as [$coefficient, $constant, $perLunation, $perT2]) {
            $jde += $coefficient * sin(deg2rad($constant + $perLunation * $k + $perT2 * $t * $t));
        }

        return $jde;
    }

    /**
     * @param array<int, array{float, list<int>, int}> $terms
     * @param list<float>                              $arguments M, M', F, Ω in radians
     */
    private static function terms(array $terms, array $arguments, float $e): float
    {
        $sum = 0.0;

        foreach ($terms as [$coefficient, $multiples, $power]) {
            $angle = 0.0;

            foreach ($multiples as $index => $multiple) {
                $angle += $multiple * $arguments[$index];
            }

            $sum += $coefficient * $e ** $power * sin($angle);
        }

        return $sum;
    }

    /**
     * The correction W that the first quarter adds and the last subtracts.
     *
     * @param list<float> $arguments
     */
    private static function quarter(array $arguments, float $e): float
    {
        [$m, $mPrime, $f] = $arguments;

        return 0.00306 - 0.00038 * $e * cos($m) + 0.00026 * cos($mPrime) - 0.00002 * cos($mPrime - $m) + 0.00002 * cos($mPrime + $m) + 0.00002 * cos(2 * $f);
    }

    /**
     * Lunations since the new moon of 2000 January 6, at a moment.
     */
    private static function lunations(DateTimeInterface $at): float
    {
        return (JulianDay::fromUtc($at) - 2451550.09766) / self::SYNODIC_MONTH;
    }
}
