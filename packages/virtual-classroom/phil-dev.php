<?php

declare(strict_types=1);

use Phil\VirtualClassroom\BigBlueButton\XmlAnswer;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'fuzz' => [
        // What a conferencing server answers and what reaches the webhook: whatever the bytes, a result or an
        // exception of the library, never another error.
        'targets' => [
            'responses' => 'tests/Fuzz/responses.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // A span that ends where it starts adds no second and moves no reach: counting it or not is the same sum.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['.*if \\(\\$left > \\$joined\\) \\{.*'],
            ],
            // The clamps only tell PHPStan the range of the result: the seconds are never negative, and the attended
            // seconds never exceed the length of the session.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return max\\(0, \\$total\\);.*',
                    '.*return max\\(0, min\\(100, intdiv.*',
                    // A title that is not UTF-8 has a length below the minimum, whatever the number under it.
                    '.*\\? mb_strlen\\(\\$title\\) : 0;.*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return max\\(0, min\\(100, intdiv.*',
                    '.*\\? mb_strlen\\(\\$title\\) : 0;.*',
                    // `explode` with a limit of 2 gives two elements at most: padding to 2 or to 3 fills the same two names.
                    '.*array_pad\\(explode\\(.=., trim\\(\\$element\\), 2\\), 2, ..\\);.*',
                ],
            ],
            // `http_build_query` writes an integer as it writes its string.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\\$parameters\\[.duration.\\] = \\(string\\) \\$room->maxMinutes;.*'],
            ],
            // The text of an element is trimmed before it reaches these digits, which cannot end with a line break.
            // In the header of Jitsi, the element is trimmed whole first.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    '.*preg_match\\(./\\^\\\\d\\{1,20\\}\\$/D., \\$created\\).*',
                    '.*preg_match\\(./\\^\\\\d\\{1,13\\}\\$/D., \\$value\\).*',
                ],
            ],
            // A moment of 10^11 is the year 5138 in seconds and 1973 in milliseconds: neither is a moment of the
            // range accepted, and a float of 0 or of 10^16 falls outside it too.
            'GreaterThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$value >= 0 && \\$value < 1e16.*',
                    '.*\\$value >= 100_000_000_000 \\? intdiv\\(\\$value, 1000\\).*',
                ],
            ],
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['.*\\$value >= 0 && \\$value < 1e16.*'],
            ],
            // What `libxml` does with the answer is the same whether the cleaning comes in a `finally`, whether the
            // flags bound the network — a DOCTYPE is refused after the load, and no entity is loaded —, whether the
            // blank answer is cut before the load, which fails on it, and whether the root is read with a null-safe
            // operator, the root of a loaded document never being null.
            'UnwrapFinally' => ['ignore' => [XmlAnswer::class.'::read']],
            'BitwiseOr' => ['ignore' => [XmlAnswer::class.'::read']],
            'UnwrapTrim' => ['ignore' => [XmlAnswer::class.'::read']],
            'NullSafePropertyCall' => ['ignore' => [XmlAnswer::class.'::read']],
            // The key of the details is read only when there is a key: without one, the first test throws already.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$details = false === \\$key \\? false : openssl_pkey_get_details.*'],
            ],
            // Whether the key is false or the details are no array, the key is not a key: the details never fail on a key.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['.*false === \\$key \\|\\| !\\\\is_array\\(\\$details\\).*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside the fuzzer, which gives it what PHPStan does not know.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
