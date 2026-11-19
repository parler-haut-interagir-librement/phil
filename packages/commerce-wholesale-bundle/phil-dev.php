<?php

declare(strict_types=1);

use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API dépréciée de Symfony à remplacer.
            [
                'identifier' => 'method.deprecatedInterface',
                'path' => 'tests/Functional/BundleRegistrationTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Domain/Repository/CreditRepository.php',
                    'src/Domain/Repository/CustomerGroupRepository.php',
                    'src/Domain/Repository/QuantityRuleRepository.php',
                ],
            ],
            // The Validator's own constraints sit beside their validator, as Symfony places them.
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'path' => 'src/Infrastructure/Validator/AllowedQuantity.php',
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // At the minimum itself, the rounding up gives no step either: intdiv($increment - 1, $increment) is 0, so
            // `<` in place of `<=` suggests the same quantity. The guard only keeps intdiv() off the quantities under the
            // minimum, where it would round a negative toward zero.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$steps = \\$quantity <= \\$this->minimum \\? 0 : intdiv\\(.*',
                ],
            ],
        ],
    ],
    'rector' => [
        'skip' => [
            // A tag placed before or after the service of a package the bundle does not depend on names it by a string:
            // `::class` would make the bundle depend on that package in the eyes of Deptrac and of the dependency
            // analysis, while the constraint is ignored when the package is not installed.
            StringClassNameToClassConstantRector::class => [
                'src/PhilCommerceWholesaleBundle.php',
                'tests/Unit/PhilCommerceWholesaleBundleTest.php',
            ],
        ],
    ],
];
