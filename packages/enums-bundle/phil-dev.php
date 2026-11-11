<?php

declare(strict_types=1);

use Phil\EnumsBundle\Infrastructure\Validator\TransitionValidator;

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
                'identifier' => 'class.extendsInternalClass',
                'path' => 'tests/Unit/Infrastructure/MakeEnumTest.php',
                'count' => 1,
            ],
            // à corriger : complexité cognitive au-dessus de 25, à découper : CheckSetColumnsCommand::execute() (27).
            [
                'identifier' => 'complexity.functionLike',
                'path' => 'src/Infrastructure/Console/CheckSetColumnsCommand.php',
                'count' => 1,
            ],
            // à corriger : API dépréciée de OpenApi à remplacer.
            [
                'identifier' => 'method.deprecatedClass',
                'path' => 'tests/Unit/Infrastructure/OpenApiTest.php',
                'count' => 3,
            ],
            // à corriger : API interne de Symfony à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'path' => 'tests/Unit/Infrastructure/MakeEnumTest.php',
                'count' => 1,
            ],
            // à corriger : API interne de Symfony à remplacer par une API publique.
            [
                'identifier' => 'method.internalClass',
                'path' => 'tests/Unit/Infrastructure/MakeEnumTest.php',
                'count' => 4,
            ],
            // à corriger : API dépréciée de OpenApi à remplacer.
            [
                'identifier' => 'new.deprecatedClass',
                'path' => 'tests/Unit/Infrastructure/OpenApiTest.php',
                'count' => 3,
            ],
            // à corriger : API interne de Symfony à remplacer par une API publique.
            [
                'identifier' => 'new.internalClass',
                'path' => 'tests/Unit/Infrastructure/MakeEnumTest.php',
                'count' => 3,
            ],
            // à corriger : API dépréciée de OpenApi à remplacer.
            [
                'identifier' => 'parameter.deprecatedClass',
                'path' => 'src/Infrastructure/OpenApi/NelmioEnumModelDescriber.php',
                'count' => 1,
            ],
            // à corriger : API dépréciée de OpenApi, of à remplacer.
            [
                'identifier' => 'property.deprecatedClass',
                'paths' => [
                    'src/Infrastructure/OpenApi/NelmioEnumModelDescriber.php',
                    'tests/Unit/Infrastructure/OpenApiTest.php',
                ],
            ],
            // à corriger : API dépréciée de OpenApi à remplacer.
            [
                'identifier' => 'staticMethod.deprecatedClass',
                'path' => 'tests/Unit/Infrastructure/OpenApiTest.php',
                'count' => 3,
            ],
            // à corriger : plusieurs classes dans un même fichier.
            [
                'identifier' => 'symplify.multipleClassLikeInFile',
                'path' => 'tests/Unit/Pair/pair.php',
                'count' => 1,
            ],
            // à corriger : déplacer les attributs dans un espace de noms Attribute (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'paths' => [
                    'src/Application/Presentation/Color.php',
                    'src/Application/Presentation/Icon.php',
                    'src/Application/Translation/Translatable.php',
                    'src/Infrastructure/Http/ParseEnum.php',
                    'src/Infrastructure/Validator/EnumCase.php',
                    'src/Infrastructure/Validator/EnumSetOf.php',
                    'src/Infrastructure/Validator/Transition.php',
                    'tests/Unit/Fixture/Priority.php',
                ],
            ],
            // à corriger : type natif manquant (ressource ou paramètre de fermeture) pour la couverture de types à 100 %.
            [
                'identifier' => 'typeCoverage.returnTypeCoverage',
                'path' => 'tests/Unit/Infrastructure/MakeEnumTest.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The name of the property `TransitionValidator` reads in Doctrine's original data: a property constraint
            // always has one, and the cast only tells PHPStan.
            'CastString' => [
                'ignore' => [
                    TransitionValidator::class.'::previous',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
