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
            // à corriger : API dépréciée de Doctrine, constant à remplacer.
            [
                'identifier' => 'classConstant.deprecatedEnum',
                'path' => 'src/Infrastructure/Doctrine/DoctrineJournalRepository.php',
                'count' => 2,
            ],
            // à corriger : API dépréciée de Doctrine à remplacer.
            [
                'identifier' => 'method.deprecated',
                'path' => 'tests/Unit/Infrastructure/DoctrineRepositoryTest.php',
                'count' => 4,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'method.internalClass',
                'path' => 'tests/Unit/Infrastructure/DoctrineRepositoryTest.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'new.internalClass',
                'path' => 'tests/Unit/PhilAiBundleTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/Port/Completions.php',
                    'src/Application/Port/UserKeys.php',
                    'src/Domain/Repository/JournalRepository.php',
                    'src/Infrastructure/SymfonyAi/UserKeyPlatformFactory.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The sums a store gives back, `tasks`, `input`, `output` and `cost`: the cast turns a numeric string or null
            // into an integer for PHPStan's rules, and `+` and `+=` read a numeric string or null as the same integer.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$count = \\(int\\) \\$group\\[.*',
                    '\\$(input|output) \\+= \\(int\\) \\$group\\[.*',
                    '\\$costs\\[.*\\(int\\) \\$group\\[.*',
                ],
            ],
        ],
    ],
];
