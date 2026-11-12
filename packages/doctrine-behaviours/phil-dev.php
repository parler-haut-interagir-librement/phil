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
            // L'identifiant est nul jusqu'à prePersist, où l'écouteur le donne : Doctrine n'écrit jamais ce null. Une
            // propriété non initialisée ferait relever property.uninitialized dans chaque entité qui prend le trait.
            [
                'identifier' => 'doctrine.columnType',
                'path' => 'src/DatedIdentifierEntity.php',
            ],
            // à corriger : API dépréciée de Doctrine, constant à remplacer.
            [
                'identifier' => 'classConstant.deprecatedEnum',
                'path' => 'src/Tree.php',
                'count' => 1,
            ],
            // à corriger : variable de boucle jamais lue.
            [
                'identifier' => 'foreach.unusedValue',
                'path' => 'tests/Benchmark/TreeBench.php',
                'count' => 1,
            ],
            // à corriger : fixture qui étend une classe concrète.
            [
                'identifier' => 'symplify.forbiddenExtendOfNonAbstractClass',
                'path' => 'tests/Unit/Fixtures/Entity/Essay.php',
                'count' => 1,
            ],
            // à corriger : déplacer les exceptions dans un espace de noms Exception (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireExceptionNamespace',
                'path' => 'src/InvalidTree.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The number of rows `executeStatement()` returns in `BlameEraser` is an integer or a numeric string that
            // `+=` converts; the depth of a node of `Tree` is only null when its path is, a case that returns `[]` just
            // before, and `null + 1` is 1 anyway. Both casts only serve PHPStan's strict rules. The count of
            // `OwnerEraser::find()` is an integer on SQLite, where the Unit suite runs, and a numeric string on drivers
            // such as pdo_mysql: the Integration suite checks the cast on MySQL and PostgreSQL. The last or the reference
            // position read by `PositionListener` and `Positions` is an integer or the numeric string of an integer
            // column, to which `+ 1` or `+ $offset` adds an integer: the sum is the same integer with the cast or
            // without, and `placeAt()` and `take()` take it as such; the cast only serves PHPStan's strict rules.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$next\\[\\$table\\]\\[\\$key\\] = is_numeric\\(\\$last\\) \\? \\(int\\) \\$last \\+ 1 : 1;.*',
                    '.*\\$this->take\\(\\$connection, \\$place, \\(int\\) \\$last \\+ 1, false\\);.*',
                    '.*\\$this->take\\(\\$connection, \\$place, \\(int\\) \\$position \\+ \\$offset, true\\);.*',
                    '.*\\$found\\[\\$table\\] = \\(int\\) \\$count;.*',
                    '\\$replaced \\+= \\(int\\) \\$this->manager->getConnection\\(\\)->executeStatement\\(.*',
                    '.*Criteria::expr\\(\\)->eq\\(TreeNode::DEPTH_FIELD, \\(int\\) \\$node->treeDepth\\(\\) \\+ 1\\).*',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // Mapping traits and ORM listeners: an infrastructure library, which sees the rest of the world, Doctrine,
        // phil/locale-dimensions for the locales of translations, and phil/slug for the slug carried by an entity.
        'ruleset' => [
            'PhilDoctrineBehaviours' => ['DefaultAllowed', 'Forbidden', 'PhilLocaleDimensions', 'PhilSlug'],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
