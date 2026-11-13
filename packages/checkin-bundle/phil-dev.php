<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    // The package's own name, and the keyed hash its manifests are made of.
    'docs' => ['public' => true, 'words' => ['checkin', 'HMAC']],
    'infection' => [
        'mutators' => [
            // The sequence read back from the table is an integer on SQLite and PostgreSQL, the drivers the unit
            // suite and most applications use; the cast is for the MySQL drivers that return numbers as strings,
            // which no unit test runs against.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    'null === \\$row\\[\'sequence\'\\] \\? null : \\(int\\) \\$row\\[\'sequence\'\\],',
                ],
            ],
        ],
    ],
];
