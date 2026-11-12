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
            // à corriger : API interne de Symfony à remplacer par une API publique.
            [
                'identifier' => 'instanceof.internalClass',
                'path' => 'tests/Unit/Infrastructure/DeprecationTest.php',
                'count' => 1,
            ],
            // à corriger : API dépréciée de Symfony à remplacer.
            [
                'identifier' => 'method.deprecatedInterface',
                'path' => 'tests/Functional/BundleRegistrationTest.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'new.internalClass',
                'path' => 'tests/Unit/PhilApiContractBundleTest.php',
                'count' => 1,
            ],
            // à corriger : API interne de Symfony à remplacer par une API publique.
            [
                'identifier' => 'property.internalClass',
                'path' => 'tests/Unit/Infrastructure/DeprecationTest.php',
                'count' => 1,
            ],
            // à corriger : forme de tableau retournée différente du type déclaré.
            [
                'identifier' => 'return.type',
                'path' => 'src/Infrastructure/Deprecation/CacheObservedDeprecations.php',
                'count' => 1,
            ],
            // à corriger : déplacer les attributs dans un espace de noms Attribute (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'paths' => [
                    'src/Infrastructure/Http/DeprecatedEndpoint.php',
                    'src/Infrastructure/Http/Idempotent.php',
                ],
            ],
            // à corriger : déplacer les exceptions dans un espace de noms Exception (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireExceptionNamespace',
                'path' => 'src/Infrastructure/Http/ProblemException.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'path' => 'src/Infrastructure/Http/IdempotencyScopeResolver.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // A file checked to exist and measured just before: `file_get_contents()` gives `false` only if it vanished
            // in between, and the empty document the cast makes of it is refused as unreadable all the same.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\\$text = \\(string\\) file_get_contents\\(\\$file\\);.*'],
            ],
        ],
    ],
    'docs' => ['public' => true],
];
