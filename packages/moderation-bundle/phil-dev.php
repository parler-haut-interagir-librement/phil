<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The driver gives the number of rows deleted or changed as an integer, or as a numeric string that the cast
            // turns into the same integer: the cast only serves the strict comparison or the sum.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    'return 1 === \\(int\\) \\$this->connection->delete\\(.*',
                    'return \\(int\\) \\$this->connection->delete\\(.*',
                    '\\$deleted = \\(int\\) \\$this->connection->delete\\(.*',
                    'return \\$deleted \\+ \\(int\\) \\$this->connection->executeStatement\\(.*',
                ],
            ],
            // The boolean given as a parameter is bound as an integer by SQLite, which every test runs on: the type only
            // makes MySQL and PostgreSQL bind a boolean, and the tests of the servers run it.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\[ParameterType::BOOLEAN\\].*',
                    "'(prompted|automated_detection)' => ParameterType::BOOLEAN,",
                    // The key is created by the line that follows, which appends a reason to it.
                    '\\$details\\[\\$key\\] \\?\\?= \\[.reasons. => \\[\\], .sources. => \\[\\]\\];',
                ],
            ],
            // A member who left the sandbox already is left as is, and the insert of a second row is refused by the
            // primary key and caught: the early return only spares the failed insert.
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => ['\\s*return;'],
            ],
            // A control character, newline included, is refused before the pattern is tried, so that `$` without `D`
            // never meets a trailing newline.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['if \\(1 === preg_match\\(./\\^\\[\\^@\\]\\+@.*'],
            ],
            // The column of a primary key is not null whatever it is declared, and the identifier columns are all keys.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    'return self::column\\(\\$name, Types::STRING, MemberId::MAX_LENGTH, true, \\$binary\\);',
                    'private static function id\\(string \\$name, bool \\$binary, bool \\$required = true\\): Column',
                ],
            ],
        ],
    ],
    'docs' => ['public' => true, 'words' => ['Fediverse']],
];
