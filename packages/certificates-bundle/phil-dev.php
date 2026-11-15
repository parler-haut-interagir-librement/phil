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
            // A reminder is dispatched for a certificate whose end is still to come, whose days left are never null: the
            // zero only satisfies the type of the event.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\$certificate->daysLeft\\(\\$now\\) \\?\\? 0.*'],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\$certificate->daysLeft\\(\\$now\\) \\?\\? 0.*'],
            ],
            // A renewal carries no completion, so the certificate it stores is always awarded now and the flag that
            // tells an award on a known completion to return the certificate found is never read.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$holder, \\$previous, null, false\\);.*'],
            ],
            // The number of a list is checked to be digits before it is multiplied: without the cast, PHP multiplies
            // the numeric string to the same integer.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['\\$from = \\(int\\) \\$number \\* \\$this->size;'],
            ],
        ],
    ],
    'fuzz' => [
        // The readers of verification codes and of the names of status lists, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'verification-code' => 'tests/Fuzz/verification-code.php',
        ],
    ],
    'docs' => [
        'public' => true,
        // The terms of the Verifiable Credentials and Data Integrity specifications, and of continuing education.
        'words' => ['Bitstring', 'cryptosuite', 'recertifying'],
    ],
];
