<?php

declare(strict_types=1);

use Phil\FaqBundle\Application\FaqPages;
use Phil\FaqBundle\Infrastructure\Doctrine\DbalUnansweredSearches;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The search as typed is cut to its first thousand bytes before it is scrubbed, so that a long query costs
            // no more than a short one: the cut to a hundred characters that follows gives the same query without it.
            // Past the questions a page holds, the loop stops: going on to skip the rest gives the same page, slower.
            'UnwrapSubstr' => [
                'ignore' => [FaqPages::class.'::page', DbalUnansweredSearches::class.'::mostFrequent'],
            ],
            'Break_' => [
                'ignore' => [FaqPages::class.'::page'],
            ],
            // `expiry` is a string of digits the pattern checked: the comparison and the date read it as the same
            // integer the cast gives.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$expiry = \\(int\\) \\$parts\\[.expiry.\\];.*',
                    '.*is_numeric\\(\\$value\\) \\? \\(int\\) \\$value : 0;.*',
                    '.*is_numeric\\(\\$row\\[.searches.\\]\\) \\? \\(int\\) \\$row\\[.searches.\\] : 0.*',
                ],
            ],
            // What a driver returns for a column: SQLite gives integers and `Y-m-d`, which the unit suite reads; MySQL
            // and PostgreSQL, which give the strings the casts and the cut to ten characters read, are tested by
            // tests/Integration, outside Infection. The 0 of a value that is not a number is a default no NOT NULL
            // integer column reaches.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*substr\\(\\$this->text\\(\\$row\\[.last_searched_on.\\]\\), 0, 10\\).*',
                    '.*is_numeric\\(\\$value\\) \\? \\(int\\) \\$value : 0;.*',
                    '.*is_numeric\\(\\$row\\[.searches.\\]\\) \\? \\(int\\) \\$row\\[.searches.\\] : 0.*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*is_numeric\\(\\$value\\) \\? \\(int\\) \\$value : 0;.*',
                    '.*is_numeric\\(\\$row\\[.searches.\\]\\) \\? \\(int\\) \\$row\\[.searches.\\] : 0.*',
                ],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['HMAC']],
    'fuzz' => [
        // A search, a vote token and a locale from a browser; a file of questions given to the import.
        'targets' => [
            'search' => 'tests/Fuzz/search.php',
            'transfer' => 'tests/Fuzz/transfer.php',
        ],
        'corpus' => 'tests/Fuzz/corpus',
    ],
    'phpstan' => [
        // The fuzz targets run inside the fuzzer, which gives them what PHPStan does not know.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
