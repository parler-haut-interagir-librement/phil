<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['Nelmio', 'NelmioCorsBundle', 'preflight', 'preflights', 'safelisted']],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The answer to a preflight, on Origin, method and requested headers nobody wrote.
        'targets' => [
            'preflight' => 'tests/Fuzz/preflight.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The port the pattern captured is digits only, and PHP compares two numeric strings as numbers: the cast to
            // an integer changes no comparison.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\(int\\) \\$port > 65535.*'],
            ],
        ],
    ],
];
