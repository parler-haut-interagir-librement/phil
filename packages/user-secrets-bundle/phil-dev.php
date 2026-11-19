<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    // The enum of PHP 8.6, through symfony/polyfill-php86 on PHP 8.5, for the order of the key rotation's query.
    'deptrac' => ['allow' => ['^SortDirection$']],
    'docs' => [
        'public' => true,
        // The names of the cryptography and of the key management services.
        'words' => ['libsodium', 'KMIP', 'Scaleway'],
    ],
    'fuzz' => [
        // The reader of sealed values — the header of format and algorithm, then the envelope —, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'sealed-value' => 'tests/Fuzz/sealed-value.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API dépréciée de Doctrine à remplacer.
            [
                'identifier' => 'method.deprecated',
                'path' => 'tests/Unit/Infrastructure/DoctrineRepositoryTest.php',
                'count' => 3,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'method.internalClass',
                'path' => 'tests/Unit/Infrastructure/DoctrineRepositoryTest.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'new.internalClass',
                'path' => 'tests/Unit/PhilUserSecretsBundleTest.php',
                'count' => 1,
            ],
            // The failures of a KMS client — unreachable, refusing — that the component does not declare, wrapped in the
            // bundle's exceptions with the client's kept as the previous one.
            [
                'identifier' => 'catch.neverThrown',
                'path' => 'src/Infrastructure/KeyManagement/KeyManagementCipher.php',
                'count' => 2,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The sort by name of an owner's secrets: SQLite reads the rows of an owner by the index of the primary key
            // (owner, name), so already by name; the sort stays written for the databases that guarantee no order
            // without `ORDER BY`.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\$this->entityManager->getRepository\\(StoredSecret::class\\)->findBy\\(\\[\'owner\' => \\$owner->value\\], \\[\'name\' => \'ASC\'\\]\\);',
                ],
            ],
            // The count of the secrets left, by key: SQLite hands it as an integer, other drivers may hand it as a string.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$counts\\[\\$row\\[\'keyId\'\\]\\] = \\(int\\) \\$row\\[\'secrets\'\\];',
                ],
            ],
            // The erasure in memory of a plaintext or a key by `sodium_memzero()`: the variable is no longer read
            // afterwards; the call shortens the time a copy of the secret stays in the memory of the process, which no
            // PHP test can observe.
            'FunctionCallRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'sodium_memzero\\(\\$(plaintext|key)\\);',
                ],
            ],
        ],
    ],
];
