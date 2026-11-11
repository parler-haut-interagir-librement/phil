<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // Ticks are multiples of the step: rounding them to the tens, as a negative count of decimals does,
                    // changes none.
                    'return number_format\(\$value, max\(0, .*',
                    // Rounding to nine, ten or eleven decimals removes the same floating point error.
                    'return round\(\$value, 10\) \+ 0\.0;',
                    // The widest label is at least 8 pixels and a band is positive: the floors of 0 and 1 are never reached.
                    '\$widest = max\(\[0, .*',
                    '\$every = max\(1, .*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'return round\(\$value, 10\) \+ 0\.0;',
                    '\$widest = max\(\[0, .*',
                ],
            ],
            'Plus' => [
                'ignoreSourceCodeByRegex' => [
                    // number_format() writes -0 as 0 since PHP 8.0: the sign of a zero tick never shows.
                    'return round\(\$value, 10\) \+ 0\.0;',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
