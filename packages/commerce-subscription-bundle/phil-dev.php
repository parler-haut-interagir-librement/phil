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
            // à corriger : API interne de PHPUnit à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'path' => 'tests/Unit/PhilCommerceSubscriptionBundleTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Domain/Repository/GiftRepository.php',
                    'src/Domain/Repository/OfferRepository.php',
                    'src/Domain/Repository/SeatRepository.php',
                    'src/Domain/Repository/SubscriptionRepository.php',
                ],
            ],
        ],
    ],
    'rector' => [
        'skip' => [
            // A tag placed after the service of a package the bundle does not depend on names it by a string: `::class`
            // would make the bundle depend on that package in the eyes of Deptrac and of the dependency analysis, while
            // the constraint is ignored when the package is not installed.
            StringClassNameToClassConstantRector::class => [
                'src/PhilCommerceSubscriptionBundle.php',
                'tests/Unit/PhilCommerceSubscriptionBundleTest.php',
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // A code draws each character uniformly from the 32 of the alphabet: a draw that leaves out the first or the
            // last character, or reads the last one through a negative offset, gives codes no test tells apart.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['\\$code \\.= self::ALPHABET\\[random_int\\(0, 31\\)\\];'],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['\\$code \\.= self::ALPHABET\\[random_int\\(0, 31\\)\\];'],
            ],
            // The count of the seats assigned before the member's comes back as an integer or as a numeric string,
            // depending on the driver, and either compares to the number of seats alike.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['return \\(int\\) \\$before < \\$seats;'],
            ],
        ],
    ],
];
