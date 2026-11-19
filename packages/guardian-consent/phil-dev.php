<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['HMAC']],
    'fuzz' => [
        // The reader of confirmation tokens on text nobody wrote, from the seeds of tests/Fuzz/corpus/<target>:
        // whatever it reads, a token is read or refused with InvalidToken, and a token read writes back to its text.
        'targets' => [
            'confirmation-token' => 'tests/Fuzz/confirmation-token.php',
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
