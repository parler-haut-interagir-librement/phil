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
        // The parser on messages nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'bounce-parser' => 'tests/Fuzz/bounce-parser.php',
        ],
    ],
];
