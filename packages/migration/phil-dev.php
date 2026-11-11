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
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'class.implementsInternalInterface',
                'path' => 'src/AnnouncingMigrator.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'classConstant.internalInterface',
                'path' => 'tests/Unit/MigrationsTest.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'instanceof.internalClass',
                'path' => 'src/AnnouncingMigrator.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'path' => 'tests/Unit/MigrationsTest.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'method.internalClass',
                'paths' => [
                    'src/AnnouncingMigrator.php',
                    'tests/Unit/MigrationsTest.php',
                ],
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'method.internalInterface',
                'paths' => [
                    'src/AnnouncingMigrator.php',
                    'tests/Unit/MigrationsTest.php',
                ],
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'new.internalClass',
                'path' => 'tests/Unit/MigrationsTest.php',
                'count' => 2,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'parameter.internalClass',
                'path' => 'src/AnnouncingMigrator.php',
                'count' => 2,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'parameter.internalInterface',
                'path' => 'src/AnnouncingMigrator.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'property.internalInterface',
                'path' => 'src/AnnouncingMigrator.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'return.internalInterface',
                'path' => 'tests/Unit/MigrationsTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les exceptions dans un espace de noms Exception (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireExceptionNamespace',
                'paths' => [
                    'src/AnnouncementsMissing.php',
                    'src/InvalidAnnouncement.php',
                ],
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Announcing.php',
                    'src/Publisher.php',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The announcing migrations extend Doctrine Migrations, log, and publish to a chat through symfony/notifier:
        // the library sees the rest of the world.
        'ruleset' => [
            'PhilMigration' => ['DefaultAllowed', 'Forbidden'],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
