<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The reader of the CSV files people upload, from the seeds of tests/Fuzz/corpus/csv.
        'targets' => [
            'csv' => 'tests/Fuzz/csv.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // `mb_convert_encoding()` from an encoding of the dialect's list never returns false: the throw only tells
            // PHPStan the text is a string.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => ['.*\\$content = false === \\$converted \\? throw .*'],
            ],
            // The characters of a set — the stops of a cell, the characters that make a cell need quotes — have no order.
            'Concat' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$stops = \\$dialect->delimiter\\."\\\\r\\\\n";.*',
                    '.*strpbrk\\(\\$cell, \\$dialect->delimiter\\.\\$quote\\."\\\\r\\\\n"\\).*',
                ],
            ],
            // `is_file()` answers first, so the include path the flag would search is never reached.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*file_get_contents\\(\\$path, false, null, 0, .*'],
            ],
            // The parts of a natural key are all there, as the key columns are required, so after `ksort()` they already are a list.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*new NaturalKey\\(array_values\\(\\$parts\\)\\).*'],
            ],
            // A file read up to two bytes past its limit is refused as one read up to one byte past it: the byte more only
            // tells a file at the limit from a larger one.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*file_get_contents\\(\\$path, false, null, 0, \\$limits->maxBytes \\+ 1\\).*'],
            ],
        ],
    ],
];
