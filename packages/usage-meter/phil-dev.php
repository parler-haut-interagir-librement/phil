<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API dépréciée de Doctrine à remplacer.
            [
                'identifier' => 'method.deprecated',
                'path' => 'tests/Unit/Dbal/DbalUsageStoreTest.php',
                'count' => 3,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'paths' => [
                    'tests/Unit/Dbal/DbalUsageStoreTest.php',
                    'tests/Unit/Dbal/UsageSchemaListenerTest.php',
                ],
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'method.internalClass',
                'path' => 'tests/Unit/Dbal/DbalUsageStoreTest.php',
                'count' => 1,
            ],
            // à corriger : constructeur d’exception restreint sous le constructeur public d’Exception, à revoir avec les constructeurs nommés.
            [
                'identifier' => 'symplify.parentMethodVisibilityOverride',
                'path' => 'src/Exception/QuotaExceeded.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/AnchorProvider.php',
                    'src/LimitProvider.php',
                    'src/Period/PeriodPolicy.php',
                    'src/UsageStore.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The count given back by `refund()` when its key is unknown: `false === $increment ||` ends the test before
            // `$given` is read, so no value of the placeholder can be seen.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$given = false === \\$increment \\? 0 : .*',
                ],
            ],
            // The same placeholder as above, for the mutator that adds one.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$given = false === \\$increment \\? 0 : .*',
                ],
            ],
            // The number of rows deleted by the purge of `DbalUsageStore`: `executeStatement()` returns an integer or a
            // numeric string that `+=` converts on its own; the cast only serves PHPStan's rules.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$forgotten \\+= \\(int\\) \\$this->connection->executeStatement\\(.*',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The core is plain PHP; the DBAL store and the subscription limits are adapters, which alone see the rest of
        // the world and the optional phil/commerce-subscription-bundle.
        'layers' => [
            'PhilUsageMeter' => '^Phil\\\\UsageMeter\\\\(?!(Dbal|Subscription)\\\\).*',
            'PhilUsageMeterAdapter' => '^Phil\\\\UsageMeter\\\\(Dbal|Subscription)\\\\.*',
        ],
        'ruleset' => [
            'PhilUsageMeter' => ['DefaultAllowed'],
            'PhilUsageMeterAdapter' => ['PhilUsageMeter', 'DefaultAllowed', 'PhilCommerceSubscriptionBundleApplication', 'Forbidden'],
        ],
    ],
];
