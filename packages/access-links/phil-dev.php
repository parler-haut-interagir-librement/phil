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
    'deptrac' => [
        // The core is plain PHP; the DBAL store and the Messenger dispatcher and handler are adapters, which alone see
        // Doctrine DBAL and Symfony Messenger.
        'layers' => [
            'PhilAccessLinks' => '^Phil\\\\AccessLinks\\\\(?!(Dbal|Messenger)\\\\).*',
            'PhilAccessLinksAdapter' => '^Phil\\\\AccessLinks\\\\(Dbal|Messenger)\\\\.*',
        ],
        'ruleset' => [
            'PhilAccessLinks' => ['DefaultAllowed', 'PhilUrl'],
            'PhilAccessLinksAdapter' => ['PhilAccessLinks', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // The reader of link tokens, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'link-token' => 'tests/Fuzz/link-token.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // An invitation identifier is 32 hexadecimal digits: as an array key it is never the integer that `strval()`
            // would give back, since a string of digits past the integer range stays a string key. DBAL answers the
            // identifiers as text, which the cast only tells PHPStan.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_map\\(strval\\(\\.\\.\\.\\), array_keys\\(\\$due\\)\\).*',
                    '.*array_map\\(\\$this->text\\(\\.\\.\\.\\), \\$this->connection->fetchFirstColumn\\(.*',
                ],
            ],
            // The copy of an invitation takes the first of the new moment and the kept one, and the callers pass the kept
            // one already in place of the new one when it is set: the order of the two never tells.
            // A single parameter of a statement is bound as text when its type is left out, which is the type it is
            // given: `[ParameterType::STRING]` and `[]` bind the same, and so do two text types and one.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\[ParameterType::STRING\\],$',
                    '.*\\[ParameterType::STRING, ParameterType::STRING\\]\\);$',
                ],
            ],
            // The limit is a positive integer by its type, which PHPStan holds the callers to: `max(1, …)` only keeps
            // a stray zero from reaching the SQL, and no test may pass one.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*ORDER BY next_reminder_at, id LIMIT %d.*max\\(1, \\$limit\\).*'],
            ],
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$respondedAt \\?\\? \\$invitation->respondedAt,.*',
                    '.*\\$revokedAt \\?\\? \\$invitation->revokedAt,.*',
                ],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['HMAC']],
];
