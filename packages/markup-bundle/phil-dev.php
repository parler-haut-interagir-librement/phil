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
            // à corriger : déplacer les exceptions dans un espace de noms Exception (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireExceptionNamespace',
                'path' => 'src/Infrastructure/Format/UnavailableFormat.php',
                'count' => 1,
            ],
        ],
    ],
];
