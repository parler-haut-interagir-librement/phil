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
                'path' => 'tests/Unit/PhilMoneyBundleTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les attributs dans un espace de noms Attribute (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'paths' => [
                    'src/Infrastructure/Validator/AcceptedCurrency.php',
                    'src/Infrastructure/Validator/MoneyRange.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The formatter by locale of `MoneyExtension`: creating it again at each call returns the same string, only
            // the caching is lost.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$this->formatters\\[\\$locale\\] \\?\\?= new NumberFormatter\\(\\$locale, NumberFormatter::CURRENCY\\);',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
