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
        'mutators' => [
            // The token pattern leaves only digits in the expiry: compared with an integer, PHP reads that numeric string as the same number.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\(int\\) \\$expiry <= \\$at->getTimestamp\\(\\).*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // Download tokens nobody signed, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'benefit-deliverables-token' => 'tests/Fuzz/benefit-deliverables-token.php',
        ],
    ],
];
