<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'new.internalClass',
                'path' => 'tests/Unit/PhilCommentsBundleTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/AuthorDirectory.php',
                    'src/Application/AuthorProvider.php',
                    'src/Application/CommentPolicy.php',
                    'src/Domain/Repository/CommentRepository.php',
                    'src/Domain/Repository/ThreadRepository.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // DBAL 4 types the affected rows as int|numeric-string: the driver of the tests, PDO SQLite, gives an int,
            // where another driver may give "1"; the cast keeps the comparison right for both.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*1 !== \\(int\\) \\$claimed.*',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // The anchoring on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'reanchoring' => 'tests/Fuzz/reanchoring.php',
        ],
    ],
];
