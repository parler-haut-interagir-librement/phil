<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // Plans of shifts nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'shift-plan' => 'tests/Fuzz/shift-plan.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The default rest between two shifts is none: the constructor of Conflicts and overlaps() both raise a
            // negative rest to zero, so a default of -1 reads the same.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*public function __construct\\(int \\$restMinutes = 0\\).*',
                    '.*public function overlaps\\(self \\$other, int \\$restMinutes = 0\\).*',
                ],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking'],
];
