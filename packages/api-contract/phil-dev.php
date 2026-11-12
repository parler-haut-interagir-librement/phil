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
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'path' => 'src/Deprecation/ObservedDeprecations.php',
                'count' => 1,
            ],
        ],
    ],
    'docs' => [
        'public' => true,
        // The names RFC 9110 gives the forms of an HTTP-date.
        'words' => ['asctime', 'fixdate'],
    ],
    'fuzz' => [
        // The verification of an OpenAPI document on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'openapi-verifier' => 'tests/Fuzz/openapi-verifier.php',
        ],
    ],
];
