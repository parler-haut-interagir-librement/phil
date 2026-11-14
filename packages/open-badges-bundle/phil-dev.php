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
        // The reader of the profiles and DID documents of other issuers, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'issuer-profile' => 'tests/Fuzz/issuer-profile.php',
        ],
    ],
    'docs' => [
        'public' => true,
        // The terms of the Verifiable Credentials and Data Integrity specifications.
        'words' => ['Multikey', 'Bitstring'],
    ],
];
