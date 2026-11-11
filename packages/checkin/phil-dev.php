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
        // The reader of the journals devices send, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'offline-batch' => 'tests/Fuzz/offline-batch.php',
        ],
    ],
];
