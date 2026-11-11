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
                'identifier' => 'method.internal',
                'path' => 'tests/Functional/FunctionalTestCase.php',
                'count' => 1,
            ],
            // à corriger : déplacer les attributs dans un espace de noms Attribute (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'path' => 'src/Infrastructure/Http/IsFeatureEnabled.php',
                'count' => 1,
            ],
            // à corriger : déplacer les exceptions dans un espace de noms Exception (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireExceptionNamespace',
                'path' => 'src/Infrastructure/Routing/FlagNotRoutable.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/Admin/ActorProvider.php',
                    'src/Application/Edit/WritableFlagDefinitions.php',
                    'src/Application/Flag/EvaluationContextResolver.php',
                    'src/Application/Flag/ExposureRecorder.php',
                    'src/Application/Toggle/ToggleAuditor.php',
                    'src/Application/Toggle/ToggleAuthorizer.php',
                    'src/Application/Toggle/WritableFlagStore.php',
                    'src/Domain/Flag/DiagnosableFlagProvider.php',
                    'src/Domain/Flag/EnumerableFlagProvider.php',
                    'src/Domain/Flag/FlagProvider.php',
                    'src/Domain/Flag/FlagStateSource.php',
                    'src/Domain/Flag/Rule/Rule.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The context key of `FlagEvaluator`: computing it again gives the same result, only the cost changes.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$key = \\$this->keys\\[\\$context\\] \\?\\?= \\$this->keyOf\\(\\$context\\);',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
