<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The weekday of `WeeklyAvailability::windows()` reads the hours by an integer key: PHP converts the
            // numeric string of `format('N')` to the same key, and the cast only serves PHPStan's types.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    'foreach \\(\\$this->hours\\[\\(int\\) \\$day->format\\(\'N\'\\)\\] \\?\\? \\[\\] as \\[\\$from, \\$until\\]\\) \\{',
                ],
            ],
            // The next start of `Bookable::next()`: `align()` rounds a moment up to the step's grid, so a moment a step
            // later less some seconds rounds to the same start, and a midnight crossed is crossed either way.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$later = \\$start \\+ \\$step \\* 60;',
                ],
            ],
            // The last day read by `WeeklyAvailability::windows()`: with `<`, a period ending at midnight stops one day
            // earlier, on the day after its last moment. A span that starts on the day left out does not meet the
            // period, and one running into it is still given beyond the period, which every reader accepts.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    'for \\(\\$day = \\$first->modify\\(\'-1 day\'\\), \\$after = \\$last->modify\\(\'\\+1 day\'\\); \\$day <= \\$after; .*',
                ],
            ],
        ],
    ],
];
