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
                'identifier' => 'new.internalClass',
                'path' => 'tests/Unit/PhilGeoBundleTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les exceptions dans un espace de noms Exception (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireExceptionNamespace',
                'path' => 'src/Infrastructure/MaxMind/UpdateFailed.php',
                'count' => 1,
            ],
            // à corriger : type natif manquant (ressource ou paramètre de fermeture) pour la couverture de types à 100 %.
            [
                'identifier' => 'typeCoverage.paramTypeCoverage',
                'path' => 'src/Infrastructure/MaxMind/DatabaseUpdater.php',
                'count' => 2,
            ],
            // à corriger : type natif manquant (ressource ou paramètre de fermeture) pour la couverture de types à 100 %.
            [
                'identifier' => 'typeCoverage.returnTypeCoverage',
                'path' => 'src/Infrastructure/MaxMind/DatabaseUpdater.php',
                'count' => 1,
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
