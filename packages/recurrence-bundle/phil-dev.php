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
                'path' => 'tests/Unit/PhilRecurrenceBundleTest.php',
                'count' => 1,
            ],
            // à corriger : ?? null superflu selon la forme déclarée, à vérifier à l’exécution.
            [
                'identifier' => 'nullCoalesce.unnecessary',
                'path' => 'src/Infrastructure/Form/RecurrenceRuleTransformer.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The ICU formatters of the rules: creating them again gives the same result, only the cost changes.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$formatter = \\$this->formatters\\[\\$locale\\]\\[\\$pattern\\].* \\?\\?= new IntlDateFormatter\\(.*',
                ],
            ],
        ],
    ],
];
