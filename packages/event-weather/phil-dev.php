<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // `implode()` and `foreach` read a list and the same values under other keys alike.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$areas = array_values\\(.*',
                    '\\$supporting = array_values\\(.*',
                    '\\$reaching = array_values\\(array_filter.*',
                ],
            ],
            // `preg_replace()` with a pattern that is valid and without the `u` flag never returns null.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['\\$code = \\(string\\) preg_replace\\(.*'],
            ],
            // A coordinate that is null is compared with the bounds as false, and false is below -90 and -180: the
            // range test throws the same site out, whichever way the null test combines.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['if \\(null === \\$longitude \\|\\| null === \\$latitude .*'],
            ],
            // The return type `?float` turns the integer back into a float.
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => ['return \\\\is_int\\(\\$value\\) \\|\\| \\\\is_float\\(\\$value\\) \\? \\(float\\) \\$value.*'],
            ],
            // A closed ring crosses the horizontal line of the point as many times on its left as on its right, so the
            // parity of the crossings on the right is the parity of those on the left.
            'LessThanNegotiation' => [
                'ignoreSourceCodeByRegex' => ['&& \\$point->longitude < \\(\\$xj - \\$xi\\).*'],
            ],
            // `number_format()` writes a negative zero as `0`: the sign of zero changes nothing.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['return number_format\\(\\(\\$degrees < 0 \\? -1 : 1\\).*'],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['Datamart', 'Locationforecast', 'MetAlerts']],
    'fuzz' => [
        // The CAP reader on documents nobody wrote, from the seeds of tests/Fuzz/corpus/cap: whatever it reads, it
        // returns alerts or throws InvalidCapDocument.
        'targets' => [
            'cap' => 'tests/Fuzz/cap.php',
        ],
    ],
    'deptrac' => [
        // The CAP alerts are read with the XML parser of PHP's DOM extension, which is outside the default and falls in
        // the Forbidden layer, as the other readers of the DOM extension allow it.
        'ruleset' => [
            'PhilEventWeather' => ['DefaultAllowed', 'PhilGeo', 'PhilUrl', 'Forbidden'],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
