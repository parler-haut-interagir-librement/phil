<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        'translations' => 'blocking',
        // The hidden line of an email that clients show beside its subject, the action of the command, and the French verb for it.
        'words' => ['preheader', 'retag', 'retags', 'dépublie', 'dépublier'],
    ],
    'infection' => [
        'mutators' => [
            // The key of a table makes its columns required: whether the column is declared nullable changes nothing.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*self::string\\(\'campaign_id\', 36, false, \\$binary\\).*'],
            ],
            // `\s*` before the end of the dimension already takes a final line feed: with or without `D`, the same values match.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['.*preg_match\\(\'/\\^\\\\s\\*\\[01\\].*'],
            ],
        ],
    ],
    'fuzz' => [
        // A token presented at the link route, anybody's: what is not a well-formed token is never looked up.
        'targets' => [
            'link-token' => 'tests/Fuzz/link-token.php',
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside the fuzzer, which gives it what PHPStan does not know.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
