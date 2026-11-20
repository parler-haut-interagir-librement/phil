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
            // `fanOut()` takes its channels in any key order and puts them in a list itself, before it hands them to the
            // Notifier: the list made here only keeps the contract of the parameter.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*\\$outside = array_values\\(array_filter\\(\\$candidates.*'],
            ],
            // A channel the sender serves has its transport, so that `supports()` has already refused a channel without one:
            // the null-safe calls only narrow the type for PHPStan.
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->(chatter|texter)\\?->send\\(\\$message\\);.*'],
            ],
            // The recipient of a chat message is read by no bridge of the bundle's own text; `asChatMessage()` ignores it.
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => ['.*\\$outgoing->asChatMessage\\(\\$contact \\?\\? new NoRecipient\\(\\), \\$transport\\).*'],
            ],
            // The tree has checked the pool before: the cast narrows the mixed value for PHPStan.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\\$pool = \\(string\\) \\$delivery\\[\'dedupe\'\\]\\[\'cache_pool\'\\];.*'],
            ],
            // When the channel names no transport, both names are the same and the second look-up repeats the first.
            'UnwrapArrayUnique' => [
                'ignoreSourceCodeByRegex' => ['.*array_unique\\(\\[str_replace.*'],
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
