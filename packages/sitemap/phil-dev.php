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
            // à corriger : API interne de PHPUnit à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'path' => 'tests/Unit/SitemapTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les exceptions dans un espace de noms Exception (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireExceptionNamespace',
                'path' => 'src/InvalidSitemap.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/UrlContainer.php',
                    'src/UrlProvider.php',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
