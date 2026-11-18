<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // Two incidents that suspend the same restriction and end at the same instant: whichever is kept, the
            // restriction ends at that instant.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['.*\\$activation->endsAt > \\$until.*'],
            ],
            // The digits of a duration, matched by `\d{1,6}`: PHP multiplies a numeric string by an integer to the
            // same integer, so the cast only tells PHPStan so.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\$seconds \\+= \\(int\\) \\$number \\* match.*'],
            ],
        ],
    ],
    'fuzz' => [
        // The reading of a duration on texts nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'duration-input' => 'tests/Fuzz/duration-input.php',
        ],
    ],
];
