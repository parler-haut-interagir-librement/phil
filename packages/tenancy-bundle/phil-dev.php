<?php

declare(strict_types=1);

use Phil\TenancyBundle\Infrastructure\Doctrine\RowLevelSecurity\TenantSession;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        'words' => ['DMARC'],
    ],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API interne de Symfony à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'path' => 'tests/Unit/Infrastructure/Console/TenantOptionTest.php',
                'count' => 1,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'new.internalClass',
                'path' => 'tests/Unit/PhilTenancyBundleTest.php',
                'count' => 1,
            ],
            // à corriger : fixture qui étend une classe concrète.
            [
                'identifier' => 'symplify.forbiddenExtendOfNonAbstractClass',
                'paths' => [
                    'tests/Unit/Infrastructure/Doctrine/Fixture/ScopedSubclass.php',
                    'tests/Unit/Infrastructure/Doctrine/Fixture/UnscopedSubclass.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // Le WeakMap ne sert que par ses clés, les connexions ouvertes : la valeur rangée en face n'est jamais lue.
            // Les pools déjà rangés et les définitions déjà parcourues ne sont lus que par `isset()`, que la valeur
            // rangée soit vraie ou fausse.
            'TrueValue' => [
                'ignore' => [TenantSession::class.'::attach'],
                'ignoreSourceCodeByRegex' => ['.*\\$(scoped|seen)\\[\\$(id|parent)\\] = true;'],
            ],
            // Les fermetures qui lisent et écrivent les métadonnées d'un élément sont gardées une fois liées : les lier
            // de nouveau à chaque appel donne la même fermeture.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => ['.*self::\\$(newTags|setNewTags|setTags) \\?\\?= .*'],
            ],
        ],
    ],
];
