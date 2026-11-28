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
    'infection' => [
        'mutators' => [
            // A cache item is a hit whatever it holds: the value stored with a replayed identifier or a held subject is
            // never read, `true` only gives the item something to keep.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*->set\\(true\\).*'],
            ],
            // The keys of a session come back as integers when they are numeric, and the callbacks that read them take
            // a string, which PHP converts for a callback called by the engine: `strval()` only tells PHPStan the keys
            // are strings. `array_slice()` and `array_find()` read the filtered keys by position or by value, so
            // `array_values()` only tells PHPStan the keys are a list.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => ['.*array_values\\(array_filter\\(array_map\\(strval\\(\\.\\.\\.\\).*'],
            ],
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*array_values\\(array_filter\\(array_map\\(strval\\(\\.\\.\\.\\).*'],
            ],
        ],
    ],
    'fuzz' => [
        // The claims of security event tokens and of Google's ID tokens, on JSON nobody wrote, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'google-claims' => 'tests/Fuzz/google-claims.php',
        ],
    ],
];
