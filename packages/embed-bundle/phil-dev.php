<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // A float is whole when floor(), ceil() and round() give it back alike: the three tests are one.
            'RoundingFamily' => [
                'ignoreSourceCodeByRegex' => ['.*floor\\(\\$value\\) === \\$value.*'],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['chromedriver', 'HMAC']],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The token decoder and the frame protocol's reference on texts nobody wrote, from tests/Fuzz/corpus/<target>.
        'targets' => [
            'embed-token' => 'tests/Fuzz/embed-token.php',
            'embed-message' => 'tests/Fuzz/embed-message.php',
        ],
    ],
];
