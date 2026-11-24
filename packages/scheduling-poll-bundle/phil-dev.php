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
            // `isset()` is true for a start held as `false` too: the value only gives the key something to keep. Doctrine
            // refreshes an entity when the hint is set, to `false` as well, as it only looks whether the hint is there.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$starts\\[\\$start\\] = true;.*',
                    '.*setHint\\(Query::HINT_REFRESH, true\\).*',
                ],
            ],
            // Every date of a poll is an option of its count: the standing is never missing, the zero only gives the
            // expression a type.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*return \\$result->standingOf\\(\\$slot->id\\)->score \\?\\? 0;.*'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*return \\$result->standingOf\\(\\$slot->id\\)->score \\?\\? 0;.*'],
            ],
            // The keys of an array are integers when they are numeric, a string cast or not: the cast only tells
            // PHPStan the keys are strings. `IntlDateFormatter::format()` gives a string for a moment of the poll, and
            // false only for one it cannot write.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$answers\\[\\(string\\) \\$slot\\] = \\$availability;.*',
                    '.*\\$date = \\(string\\) new IntlDateFormatter\\(.*',
                ],
            ],
            // The JSON type of Doctrine writes a backed enumeration as its value: the map only tells PHPStan the
            // parameter holds strings.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => ['.*array_map\\(static fn \\(Availability \\$answer\\): string => \\$answer->value, \\$answers\\), \\$timezone.*'],
            ],
            // The build of Symfony's bundle class does nothing: the call only keeps the override complete.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*parent::build\\(\\$container\\);.*'],
            ],
        ],
    ],
    'js' => ['paths' => ['assets/src', 'tests/js']],
];
