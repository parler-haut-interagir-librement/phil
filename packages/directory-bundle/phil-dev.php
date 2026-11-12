<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'words' => ['larp', 'larps', 'noarchive']],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API interne de PHPUnit à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'path' => 'tests/Unit/InfrastructureTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/RelatedLookup.php',
                    'src/Application/UserErasure.php',
                    'src/Domain/Capability/AddressCapable.php',
                    'src/Domain/Capability/ContactCapable.php',
                    'src/Domain/Capability/EditedByCapable.php',
                    'src/Domain/Capability/LocationCapable.php',
                    'src/Domain/Capability/RelatedCapable.php',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // A listing is a content: its domain uses the capability traits of phil/content-bundle, which carry the slug,
        // the URL and the marked-up body of their own libraries.
        'ruleset' => [
            'PhilDirectoryBundleDomain' => ['DefaultAllowed', 'PhilAddress', 'PhilAddressBundleDomain', 'PhilContact', 'PhilContactBundleDomain', 'PhilContentBundleDomain', 'PhilDddBundleDomain', 'PhilGeo', 'PhilGeoBundleDomain', 'PhilIndexingConsent', 'PhilLocaleDimensions', 'PhilSitemap', 'PhilSlug', 'PhilUrl', 'PhilMarkup'],
        ],
    ],
];
