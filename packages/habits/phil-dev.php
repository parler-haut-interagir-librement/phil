<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The core is plain PHP; the DBAL stores are an adapter, which alone sees Doctrine.
        'layers' => [
            'PhilHabits' => '^Phil\\\\Habits\\\\(?!Dbal\\\\).*',
            'PhilHabitsAdapter' => '^Phil\\\\Habits\\\\Dbal\\\\.*',
        ],
        'ruleset' => [
            'PhilHabits' => ['DefaultAllowed'],
            'PhilHabitsAdapter' => ['PhilHabits', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // The schedule rules and days on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'schedule-rule' => 'tests/Fuzz/schedule-rule.php',
            'day' => 'tests/Fuzz/day.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The assertion only tells static analysis the range of the percentage: both halves are true.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['\\\\assert\\(\\$percent >= 0 && .*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking'],
];
