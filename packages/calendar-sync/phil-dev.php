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
            // Margins and bounds that only change how much is read, never what is returned: the days kept before the
            // window for an occurrence that changes time are a margin, any from an hour to years gives the same periods;
            // one start more or two more than the limit throw alike at the first period over; a duration always has its
            // weeks group, a numeric string `(int)` reads as it is.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$from = \\$periods->window->from->modify\\(.*',
                    'foreach \\(Recurrence::of\\(.*->between\\(\\$from, \\$periods->window->to, BusyPeriods::MAX \\+ 1\\).*',
                    '\\$days = 7 \\* \\(int\\) \\(\\$match\\[2\\] \\?\\? 0\\).*',
                    // A date is read through `moment()`, which trims it and keeps the part before a slash: the same
                    // date whatever surrounds it or follows the first slash.
                    '\\$dates\\[\\] = self::moment\\(\\$parameters, explode.*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$from = \\$periods->window->from->modify\\(.*',
                    '\\$days = 7 \\* \\(int\\) \\(\\$match\\[2\\] \\?\\? 0\\).*',
                ],
            ],
            // `moment()` trims what it reads, and so does the zone; a line of a calendar has neither a newline nor a
            // carriage return left once cut, so `$` and `$`/D match the same text.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(1 === preg_match\\(\'/\\^\\\\d\\{8\\}\\$/D\', \\$value\\)\\) \\{',
                    'if \\(1 !== preg_match\\(\'/\\^\\\\d\\{8\\}T\\\\d\\{6\\}Z\\?\\$/D\', \\$value\\)\\) \\{',
                    'if \\(1 !== preg_match\\(\'/\\^\\[\\\\x21-\\\\x7E\\]\\[\\\\x20-\\\\x7E\\]\\{0,63\\}\\$/D\', \\$name\\)\\) \\{',
                ],
            ],
            // The dates of a list go through `moment()`, which trims them: the same date with or without the spaces
            // around it or its slash.
            'UnwrapTrim' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$dates\\[\\] = self::moment\\(\\$parameters, explode.*',
                    // `RecurrenceRule::parse()` trims the rule it reads.
                    'null === \\$rule \\? null : trim\\(\\$rule\\),',
                    // An XML text of spaces only is no document either: `loadXML()` refuses it as it refuses the empty one.
                    'if \\(\'\' === trim\\(\\$body\\) \\|\\| !\\$document->loadXML.*',
                ],
            ],
            // Looking for a control character in two texts joined, the order of the two changes nothing.
            'Concat' => [
                'ignoreSourceCodeByRegex' => [
                    '1 === preg_match\\(\'/\\[\\\\x00-\\\\x1F\\\\x7F\\]/\', \\$id\\.\\$calendar\\) => .*',
                    'if \\(\'\' === \\$user \\|\\| str_contains\\(\\$user, \':\'\\) \\|\\| 1 === preg_match\\(.*',
                ],
            ],
            // A date written in full by `DATE_ATOM` has every field the format reads: the `!` that resets the others
            // changes nothing.
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$from = DateTimeImmutable::createFromFormat\\(\'!\'\\.DateTimeInterface::ATOM, \\$this->from\\);',
                ],
            ],
            // An event with no rule, no added date and no removed one is one occurrence: read through its rule of a
            // single day it gives the same period as the shortcut `isSingle()` takes. The arm of an overridden
            // occurrence, joined to it on the same line, is killed by its own test.
            'MatchArmRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'null !== \\$event->recurrenceId, \\$event->isSingle\\(\\) => .*',
                ],
            ],
            // The number of days between two dates is a numeric string `sprintf('%+d')` reads as the integer it is.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$days = \\(int\\) new DateTimeImmutable.*',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The rules of `phil/recurrence` expand an event's occurrences; `phil/safe-http` reads the feeds; `phil/url`
        // checks every address; Symfony's HttpClient and Clock are the transports and the time of the adapters.
        'ruleset' => [
            'PhilCalendarSync' => ['DefaultAllowed', 'Forbidden', 'PhilRecurrence', 'PhilSafeHttp', 'PhilUrl'],
        ],
    ],
    'fuzz' => [
        // The iCalendar reader, on texts nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'calendar-sync-icalendar' => 'tests/Fuzz/calendar-sync-icalendar.php',
        ],
    ],
];
