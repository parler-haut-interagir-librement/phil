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
            // à corriger : type d’argument trop large relevé par bleedingEdge.
            [
                'identifier' => 'argument.type',
                'path' => 'src/LoupeIndex.php',
                'count' => 3,
            ],
        ],
    ],
    'deptrac' => [
        // The adapter implements the index port of phil/search-bundle with Loupe, which stays in this package because
        // of the licence it pulls: it sees the domain and the application of the bundle, and the rest of the world.
        'ruleset' => [
            'PhilSearchLoupe' => ['DefaultAllowed', 'PhilSearchBundleDomain', 'PhilSearchBundleApplication', 'Forbidden'],
        ],
    ],
    'docs' => [
        // The package is released from its own repository: its CHANGELOG uses every section.
        'public' => true,
        // The search engine the bundle's other adapter talks to.
        'words' => ['Meilisearch'],
    ],
];
