<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // A browser's subscription as anyone could send it, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'push-subscription' => 'tests/Fuzz/push-subscription.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // OpenSSL gives no key only when it has no elliptic curves; `true` in its place reads no `ec` either, and the
            // result is the same refusal.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$details = false === \\$key \\? false : openssl_pkey_get_details\\(\\$key\\);.*'],
            ],
            // The search for the longest beginning that fits is monotone: an upper bound one byte off (or two) only holds
            // candidates whose cut plus its ellipsis is longer than the whole text, which never fits, so the result is the same.
            'Minus' => [
                'ignoreSourceCodeByRegex' => ['.*\\$high = \\\\strlen\\(\\$text\\) - 1;.*'],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\$high = \\\\strlen\\(\\$text\\) - 1;.*'],
            ],
            // A middle one step higher (or an upper bound one off) changes the path of the halving, not the length it ends on.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$high = \\\\strlen\\(\\$text\\) - 1;.*',
                    '.*\\$middle = intdiv\\(\\$low \\+ \\$high \\+ 1, 2\\);.*',
                ],
            ],
        ],
    ],
];
