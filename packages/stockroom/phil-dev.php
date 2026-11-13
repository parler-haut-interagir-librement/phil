<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // A list lacking a negative number of pieces takes none, as one lacking none does: the loop of `allocate()` only takes above 0.
                    '.*\\$lacking = max\\(0, \\$total - .*',
                    // A count left under 0 is dropped by the `array_filter()` that keeps what is above 0, as one left at 0 is.
                    '.*\\$counted\\[\\$holding->item\\] = \\(\\$counted.*',
                    // Zero lacking is the quick exit of a loop that takes nothing from any container.
                    '.*if \\(0 === \\$lacking \\|\\| .*',
                    // A container the item is not held in takes nothing, whether it counts 0 or -1 held.
                    '.*\\$take = min\\(\\$left, \\$cap, .*',
                ],
            ],
            // The keys of `$held` are strings or, for digits only, integers, and every use of the key takes either the same way.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$passes\\[\\] = \\[\\(string\\) \\$line->from, .*',
                    '.*\\$passes\\[\\] = \\[\\(string\\) \\$container, .*',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // The reader of load lists written as text, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'load-list' => 'tests/Fuzz/load-list.php',
        ],
    ],
];
