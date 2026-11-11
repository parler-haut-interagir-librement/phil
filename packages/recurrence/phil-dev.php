<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // The start of the walk: a cursor set one week earlier stays aligned on the interval, `between()` and
            // `next()` drop the earlier occurrences, and no weekly rule has the 10,000 empty weeks in a row that would
            // reach the limit.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'Frequency::Weekly => intdiv\\(\\$elapsed->days, 7\\),',
                ],
            ],
            // The monthly start, counted on eleven months a year: the cursor starts from an older period, always
            // aligned, and the extra occurrences are dropped; no monthly rule has enough empty months in a row to reach
            // the limit of 10,000.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'Frequency::Monthly => \\$elapsed->y \\* 12 \\+ \\$elapsed->m,',
                ],
            ],
            // The end bound of the walk of `Recurrence::candidates()`: without it, the walk goes further, but
            // `between()` returns from the first occurrence after the end, and otherwise the limit of empty periods or
            // the year 9999 stops it. The result is the same, only the duration changes.
            'LogicalOrAllSubExprNegation' => [
                'ignoreSourceCodeByRegex' => [
                    'while \\(\\$walked - \\$lastWithDays < self::MAX_PERIODS && .*\\$cursor->format\\(\'Y-m-d\'\\) <= \\$last\\)\\) \\{',
                ],
            ],
            // The year of a date of `RecurrenceDates::of()` and the fields of a date read by `RecurrenceDates::date()`:
            // compared with an integer, a string of digits is compared as a number, and a missing hour of a day is
            // null, which no bound exceeds; the year 0 is refused by `checkdate()` as well.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$year = \\(int\\) \\(\\$isDate \\? \\$moment : \\$moment->setTimezone\\(\\$written\\)\\)->format\\(\'Y\'\\);',
                    'if \\(\\(int\\) \\$parts\\[\'year\'\\] < 1 \\|\\| !checkdate\\(.*',
                ],
            ],
            // The end of a date and of a zone name read before a final newline: a date is trimmed before it is read,
            // and PHP refuses a zone name that ends with a newline, with the same message.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(1 !== preg_match\\(\'/\\^\\(\\?<year>.*',
                    'if \\(1 !== preg_match\\(\'~\\^\\[A-Za-z0-9_.*',
                ],
            ],
            // The monthly start with the months subtracted: the same reasoning as the count on eleven months.
            'Plus' => [
                'ignoreSourceCodeByRegex' => [
                    'Frequency::Monthly => \\$elapsed->y \\* 12 \\+ \\$elapsed->m,',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // The reader of RDATE and EXDATE lines on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'recurrence-dates' => 'tests/Fuzz/recurrence-dates.php',
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
