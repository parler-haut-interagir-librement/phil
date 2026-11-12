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
        'ignoreErrors' => [
            // The bundle's test registers DoctrineBundle's extension to see the mapping prepended to it, as the kernel does.
            [
                'identifier' => 'new.internalClass',
                'path' => 'tests/Unit/PhilFormsAutosaveBundleTest.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // DBAL 4 types the affected rows as int|numeric-string: the driver of the tests, PDO SQLite, gives an int,
            // where another driver may give "1"; the cast keeps the comparison right for both.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    'return 1 === \\(int\\) \\$this->connection->update\\(.*',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // The reader of the drafts stored on the server, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'draft' => 'tests/Fuzz/draft.php',
        ],
    ],
    'docs' => ['public' => true],
];
