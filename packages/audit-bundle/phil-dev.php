<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The answer of a segment is a pure function of the segment and the tenant: keeping it for the next rows only
            // saves the hashing, and computing it again every time gives the same answer.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->admitted\\[\\$tenant\\] \\?\\?= .*'],
            ],
            // The identifier filter is reached only after the type filter, which a query sets with it and a subject always
            // has: the subject of a row that passes the one is never `null` for the other.
            'NullSafePropertyCall' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->subjectId === \\$facts->subject\\?->id.*'],
            ],
        ],
    ],
    'fuzz' => [
        // The page cursor and the site segments on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'cursor-token' => 'tests/Fuzz/cursor-token.php',
            'site-segment' => 'tests/Fuzz/site-segment.php',
        ],
    ],
    'docs' => ['public' => true, 'words' => ['HMAC']],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : complexité cognitive au-dessus de 25, à découper : ChainTest::judged() (29).
            [
                'identifier' => 'complexity.functionLike',
                'path' => 'tests/Unit/Domain/Chain/ChainTest.php',
            ],
            // à corriger : API dépréciée de Doctrine à remplacer.
            [
                'identifier' => 'method.deprecated',
                'path' => 'tests/Unit/Infrastructure/StorageTest.php',
                'count' => 3,
            ],
            // à corriger : API dépréciée de Symfony à remplacer.
            [
                'identifier' => 'method.deprecatedInterface',
                'path' => 'tests/Functional/BundleRegistrationTest.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'method.internalClass',
                'path' => 'tests/Unit/Infrastructure/StorageTest.php',
                'count' => 1,
            ],
        ],
    ],
];
