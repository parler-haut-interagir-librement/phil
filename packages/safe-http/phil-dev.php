<?php

declare(strict_types=1);

use Phil\SafeHttp\SafeHttpClient;

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'deptrac' => [
        // The core of PHP outside the default: the WHATWG parser of the URI extension resolves a redirect's address
        // against the one that answered, before phil/url checks it.
        'ruleset' => [
            'PhilSafeHttp' => ['DefaultAllowed', 'PhilUrl', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // The guard of the first address and of each redirect, on addresses nobody wrote, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'safe-http-addresses' => 'tests/Fuzz/safe-http-addresses.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The time left is a float of nanoseconds read twice from the clock: it is never exactly 0 where a
            // read follows the deadline, so `<=` and `<` cannot be told apart.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['if \\(\\$remaining <= 0\\) \\{'],
            ],
            // Symfony's NoPrivateNetworkHttpClient reads this option to follow redirects itself, then hands 0 to the
            // client it wraps: the mock only ever sees 0, and the redirects followed here are counted by the tests.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ["'max_redirects' => 0,"],
            ],
            // The search of the stop mark starts one byte before the earliest place where a mark can newly end: a
            // start one byte earlier finds the same marks, as a mark that ended earlier already stopped the read.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*stripos\\(\\$body, \\$mark, max\\(0, .*'],
            ],
            'Plus' => [
                'ignoreSourceCodeByRegex' => ['.*stripos\\(\\$body, \\$mark, max\\(0, .*'],
            ],
            // A cancelled response yields no more chunk: `continue` ends the loop as `break` does.
            'Break_' => [
                'ignore' => [SafeHttpClient::class.'::read'],
            ],
        ],
    ],
];
