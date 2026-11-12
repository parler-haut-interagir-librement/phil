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
    'infection' => [
        'mutators' => [
            // The seconds left in a period are its length minus the remainder of a non-negative timestamp, so 1 to the
            // length and never 0: `> 0` and `>= 0` cannot be told apart. The assertion only tells PHPStan so.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['.*assert\\(\\$secondsLeft > 0\\);'],
            ],
        ],
    ],
    'fuzz' => [
        // The readers of addresses and networks, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'ip-address' => 'tests/Fuzz/ip-address.php',
            'ip-network' => 'tests/Fuzz/ip-network.php',
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
