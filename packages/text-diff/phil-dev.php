<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The worker-mode page runs inside FrankenPHP, which gives it `frankenphp_handle_request()`, unknown to PHPStan.
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Worker/*', 'tests/Fuzz/*'],
    ],
    'fuzz' => [
        // Each parser on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'tokenizer' => 'tests/Fuzz/tokenizer.php',
        ],
    ],
    // The page `composer bench:worker phil/text-diff` measures, served by FrankenPHP in worker mode.
    'bench' => [
        'worker' => ['script' => 'tests/Worker/worker.php', 'requests' => 1000, 'warmup' => 50],
    ],
];
