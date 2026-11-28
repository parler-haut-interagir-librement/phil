<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['dépublie', 'déséchappé', 'déséchappée']],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'deptrac' => [
        // The rules and the dates of `phil/recurrence` read RRULE, RDATE and EXDATE; `phil/safe-http` reads the feeds;
        // `phil/url` checks every address.
        'ruleset' => [
            'PhilCalendarImport' => ['DefaultAllowed', 'Forbidden', 'PhilRecurrence', 'PhilSafeHttp', 'PhilUrl'],
        ],
    ],
    'fuzz' => [
        // The iCalendar reader, on texts nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'ics-reader' => 'tests/Fuzz/ics-reader.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // A value read from a line holds no line break — the text is cut at every one, CR, LF or CRLF — and the dates
            // are trimmed: `$` without `D` matches nothing more.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    '.*preg_match\\(\'/\\^\\[A-Za-z0-9-\\]\\{1,64\\}\\$/D\', \\$value\\).*',
                    '.*preg_match\\(\'/\\^\\\\d\\{8\\}\\$/D\', \\$value\\).*',
                    '.*preg_match\\(\'/\\^\\\\d\\{8\\}T\\\\d\\{6\\}Z\\?\\$/D\', \\$value\\).*',
                    '.*preg_match\\(\'/\\^\\\\d\\{1,9\\}\\$/D\', \\$value\\).*',
                    '.*preg_match\\(\'/\\^\\\\d\\{8\\}T\\\\d\\{6\\}Z\\$/D\', \\$value\\).*',
                    '.*preg_match\\(\'/\\^\\(\\[\\+-\\]\\(\\?:0\\\\d\\|1\\[0-8\\]\\)\\[0-5\\]\\\\d\\)\\(\\?:00\\)\\?\\$/D\'.*',
                ],
            ],
            // Of two events of the same key, the comparison runs only when the sequences, or the moments to the second,
            // differ: `>=` never meets two equal ones.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return \\$event->sequence > \\$first->sequence \\? \\$event : \\$first;.*',
                    '.*return \\$late > \\$early \\? \\$event : \\$first;.*',
                ],
            ],
            // The zones of a TZID are kept for the rest of the read only to spare the work: resolving one again gives the
            // same zone.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => ['.*return \\$this->resolved\\[\\$tzid\\] \\?\\?= \\$this->find\\(\\$tzid\\);.*'],
            ],
            // One step more reads the empty name, which no zone has.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['.*for \\(\\$from = 1, \\$count = \\\\count\\(\\$segments\\); \\$from < \\$count .*'],
            ],
            // The offsets are the keys of the array; what they hold is never read.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$offsets\\[\\$line->value\\] = true;.*'],
            ],
            // The power only stays an integer: the shortest retry doubled 14 times already passes the longest backoff.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*min\\(\\$this->backoff, \\$this->retry \\* 2 \\*\\* min\\(20, .*',
                    // PHP reads an offset written with its seconds, +hhmm00, as +hhmm: the whole match is the same zone.
                    '.*return new DateTimeZone\\(\\$match\\[1\\]\\);.*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*min\\(\\$this->backoff, \\$this->retry \\* 2 \\*\\* min\\(20, .*'],
            ],
            // PHP turns a key that is a whole number into an integer, cast or not: the cast only tells PHPStan so.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\\$read\\[\\(string\\) \\$key\\] = EventVersion::fromArray\\(\\$version\\);.*'],
            ],
        ],
    ],
];
