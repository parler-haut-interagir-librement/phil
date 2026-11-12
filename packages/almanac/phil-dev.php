<?php

declare(strict_types=1);

use Phil\Almanac\Astronomy\Eclipses;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        // The package is released from its own repository: its CHANGELOG uses every section.
        'public' => true,
        // Jean Meeus, author of Astronomical Algorithms, and its publisher; Fred Espenak, whose polynomials give ΔT.
        'words' => ['Meeus', "Meeus's", 'Willmann', 'Espenak'],
    ],
    'phpstan' => [
        // The fuzz target runs under nikic/php-fuzzer, which only vendor-bin/fuzzer installs.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Rule/Rule.php',
                    'src/Rule/WithinTheYear.php',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // The rule parser on texts nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'rule-parser' => 'tests/Fuzz/rule-parser.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // Three terms of the Easter computus change no date from 1583 to 4099, the range the library covers:
            // `intdiv($b + 8, 25)` only differs from its neighbours in centuries 16 and 40, where `$g` stays the same;
            // the numerator of `$m` never reaches 450; `- 7 * $m` only changes the month for values of `$h + $l` no
            // year has when `$m` is 1. The test compares every year of the range with Gauss's algorithm: no year shows
            // these mutants. The second month after the eleventh, in `ChineseNewYear::of()`, only counts for a twelfth
            // intercalary month no year of the range has.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$f = intdiv\\(\\$b \\+ 8, 25\\);',
                    '\\$month = intdiv\\(\\$h \\+ \\$l - 7 \\* \\$m \\+ 114, 31\\);',
                    '\\$second = self::hasPrincipalTerm\\(.*',
                    '\\$at = \\$midnight \\+ 0\\.5 - \\$longitude / 360;',
                    'for \\(\\$i = 0; \\$i < self::ITERATIONS; \\+\\+\\$i\\) \\{',
                    'return \\$julian \\+ JulianDay::deltaT\\(2000 \\+ \\(\\$julian - 2451545\\.0\\) / 365\\.25\\) / 86400;',
                ],
            ],
            // See `IncrementInteger`: the same terms of the computus and of `ChineseNewYear::of()`.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$f = intdiv\\(\\$b \\+ 8, 25\\);',
                    '\\$m = intdiv\\(\\$a \\+ 11 \\* \\$h \\+ 22 \\* \\$l, 451\\);',
                    '\\$month = intdiv\\(\\$h \\+ \\$l - 7 \\* \\$m \\+ 114, 31\\);',
                    '\\$second = self::hasPrincipalTerm\\(.*',
                    '\\$at = \\$midnight \\+ 0\\.5 - \\$longitude / 360;',
                    'for \\(\\$i = 0; \\$i < self::ITERATIONS; \\+\\+\\$i\\) \\{',
                    'return \\$julian \\+ JulianDay::deltaT\\(2000 \\+ \\(\\$julian - 2451545\\.0\\) / 365\\.25\\) / 86400;',
                ],
            ],
            // The last lunation `MoonPhases::between()` walks: `ceil()` takes it far enough for a new moon ahead of its
            // mean, and so does `round()`, since such a moon is always more than half a lunation after the previous
            // one. `floor()`, which is wrong, stays mutated and killed.
            'RoundingFamily' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$last = ceil\\(self::lunations\\(\\$until\\)\\);',
                    '.*\\$last = min\\(self::LAST_LUNATION, ceil\\(.*',
                ],
            ],
            // The fixed-point iterations of `SunTimes`, noon and each crossing, start from a guess whose error the
            // iteration divides by about three thousand at each step — the equation of time and the declination
            // change by seconds in a day, and are read in minutes of a day of 1440: three steps leave less than a
            // millisecond, a fourth or a fifth changes no second, whatever the guess is or how many steps follow. The
            // test of the whole range, to the second, in places of every latitude, shows none. The same goes for the
            // seconds of the Julian Ephemeris Day, `/ 86400`: ΔT is at most 6 000 s from 1000 to 3000, and an error of
            // one part in 86 400 of it is 0.07 s, and a millisecond of the Sun's position.
            // The equalities of two floats that no input reaches, in `SunTimes` the cosine of the hour angle at 1 and
            // at −1, and in `Eclipses` the distance of the shadow's axis, the radius of the umbra, the magnitudes, the
            // `u` of the Moon's shadow and `|sin F|` at their limits: the comparison to either side gives the same
            // result for every syzygy of the range, the tests over each of its lunations show.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$distance > 1\\.5433 \\+ \\$u.*',
                    '.*\\$u > 0\\.0047.*',
                    '.*abs\\(sin\\(\\$f\\)\\) > self::NEAR_A_NODE.*',
                    '.*\\$umbral > 0 =>.*',
                    '.*\\$umbral > 0 \\? .*',
                    '.*\\$cosine > 1 =>.*',
                    '.*\\$first > \\$last \\? .*',
                ],
            ],
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$distance < 0\\.9972.*',
                    '.*\\$u < 0 \\?.*',
                    '.*\\$u < 0\\) \\{.*',
                    '.*\\$u < 0\\.00464 \\* sqrt.*',
                    '.*\\$cosine < -1 =>.*',
                    'for \\(\\$i = 0; \\$i < self::ITERATIONS; \\+\\+\\$i\\) \\{',
                ],
            ],
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$penumbral <= 0.*',
                ],
            ],
            'GreaterThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$umbral >= 1.*',
                ],
            ],
            // `SunTimes`, the noon: the first guess of the iteration, see `IncrementInteger`.
            'Plus' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$at = \\$midnight \\+ 0\\.5 - \\$longitude / 360;',
                ],
            ],
            'Minus' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$at = \\$midnight \\+ 0\\.5 - \\$longitude / 360;',
                    // The last lunation `Eclipses::lunations()` walks: further than the real one, the walk only goes on
                    // through lunations whose eclipses the period's bounds leave out, with the same result.
                    '.*\\$last = min\\(self::LAST_LUNATION, ceil\\(.*',
                ],
            ],
            'Division' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$at = \\$midnight \\+ 0\\.5 - \\$longitude / 360;',
                    // The first lunation `Eclipses::lunations()` walks: the first of the range instead of the one of the
                    // period, whose eclipses before the bound are left out as the filter of `solar()` and `lunar()` does.
                    '.*\\$first = max\\(self::FIRST_LUNATION, floor\\(.*',
                ],
            ],
            // `Eclipses::lunations()`: `round()` ends the walk at the nearest lunation, and the greatest of an eclipse
            // is within a day of its mean syzygy, a thirtieth of a lunation: the lunation of every eclipse still falls
            // within the walk. And `Eclipses::syzygy()` leaves a syzygy far from a node at once, which only spares the
            // computation of an eclipse the next steps would refuse; `Eclipses::centralKind()` returns `Annular` for a
            // `u` above 0.0047, the line below does the same for it.
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'return SolarEclipseKind::Annular;',
                ],
                'ignore' => [
                    Eclipses::class.'::syzygy::186',
                ],
            ],
            // `Crossing::seconds()`: a crossing that crosses has both its instants — `Crossing::at()` is the only way to
            // make one —, the casts and the null-safe calls only satisfy the types of the properties.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*Altitude::Crosses => .*',
                ],
            ],
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => [
                    '.*Altitude::Crosses => .*',
                ],
            ],
            // The memo of the Chinese New Year by year: computing it again gives the same result, only the cost
            // changes.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    'self::\\$years\\[\\$year\\] \\?\\?= self::computed\\(\\$year\\);',
                ],
            ],
        ],
    ],
];
