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
    'fuzz' => [
        // The loader and the readers of stored data on input nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'catalogue-definition' => 'tests/Fuzz/catalogue-definition.php',
            'stored-warnings' => 'tests/Fuzz/stored-warnings.php',
        ],
        'budget' => 20,
    ],
];
