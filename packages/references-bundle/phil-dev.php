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
                'path' => 'tests/Unit/Infrastructure/DoctrineTest.php',
                'count' => 4,
            ],
            // à corriger : API interne de Doctrine, PHPUnit à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'paths' => [
                    'tests/Benchmark/BrokenReferencesBench.php',
                    'tests/Benchmark/UsageReplaceBench.php',
                    'tests/Unit/Infrastructure/DoctrineTest.php',
                    'tests/Unit/Infrastructure/TrackingTest.php',
                ],
            ],
            // à corriger : constructeur d’exception restreint sous le constructeur public d’Exception, à revoir avec les constructeurs nommés.
            [
                'identifier' => 'symplify.parentMethodVisibilityOverride',
                'path' => 'src/Domain/Exception/StillReferenced.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/ReferenceCollector.php',
                    'src/Application/ReferenceTarget.php',
                    'src/Domain/Repository/UsageRepository.php',
                ],
            ],
        ],
    ],
];
