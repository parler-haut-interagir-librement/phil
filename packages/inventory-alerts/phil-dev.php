<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        // Under coverage, the tests that cover the checks and the planner take more than a minute together (the bound
        // of 100,000 rules builds 200,001 rules): with a shorter timeout, Infection skips their mutants untested.
        'timeout' => 300,
        'mutators' => [
            // An integer is compared with 1e12 the same as with 1,000,000,000,000: both are exact.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$value > \\(int\\) self::MAX_UNITS.*',
                    // A fixed-quantity rule always has its order quantity: the cast only tells PHPStan.
                    '.*\\$quantity = \\(int\\) \\$this->orderQuantity;.*',
                ],
            ],
            // Every bound is a multiple of ten, which -1 decimals write the same.
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*return number_format\\(\\$value, 0, .*']],
            // isset() is true for false as for true: only the key is read.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['.*\\$seen\\[\\$key\\] = true;.*']],
        ],
    ],
];
