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
        // The reader of pages parses HTML through the DOM extension of PHP, which is outside the default.
        'ruleset' => [
            'PhilStructuredData' => ['DefaultAllowed', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // The reader of the JSON-LD of a page, from the seeds of tests/Fuzz/corpus/jsonld-reader.
        'targets' => [
            'jsonld-reader' => 'tests/Fuzz/jsonld-reader.php',
        ],
    ],
    'docs' => [
        // The vocabulary and the formats the library speaks.
        'words' => ['JSON-LD', 'schema.org', 'BCP', 'IRI', 'ISBN', 'GTIN'],
    ],
];
