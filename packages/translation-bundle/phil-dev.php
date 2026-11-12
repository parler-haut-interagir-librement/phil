<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API dépréciée de Doctrine à remplacer.
            [
                'identifier' => 'method.deprecated',
                'path' => 'tests/Unit/Infrastructure/DoctrineRepositoryTest.php',
                'count' => 7,
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
                'path' => 'tests/Unit/PhilTranslationBundleTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/SourceDocuments.php',
                    'src/Application/TranslationPolicy.php',
                    'src/Domain/Repository/SegmentRepository.php',
                    'src/Domain/Repository/TermRepository.php',
                ],
            ],
        ],
    ],
    'docs' => ['public' => true],
];
