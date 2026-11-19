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
            // The number of rows `executeStatement()` returns in `BlameEraser` and `OwnerEraser` is an integer or a numeric string that
            // `+=` converts; the depth of a node of `Tree` is only null when its path is, a case that returns `[]` just
            // before, and `null + 1` is 1 anyway. Both casts only serve PHPStan's strict rules. The count of
            // `OwnerEraser::find()` is an integer on SQLite, where the Unit suite runs, and a numeric string on drivers
            // such as pdo_mysql: the Integration suite checks the cast on MySQL and PostgreSQL. The last or the reference
            // position read by `PositionListener` and `Positions` is an integer or the numeric string of an integer
            // column, to which `+ 1` or `+ $offset` adds an integer: the sum is the same integer with the cast or
            // without, and `placeAt()` and `take()` take it as such; the cast only serves PHPStan's strict rules. The
            // identifier of a version and the id of a loaded version are an integer or the numeric string of one, which
            // is an integer as an array key: the cast changes no key. The assertions of `VersionEraser::rows()` check
            // what the database returns and never run in production. `VersionEraser` numbers the placeholders of a
            // link query from zero and reads the versions after the identifier zero, the first of a table being one:
            // the starting key of a positional list and a bound that no identifier reaches change nothing. The
            // identifiers a link query returns are strings or integers and are bound as a list of strings either way;
            // the keys of the links of a table and of the stores are never read. The key of a store joins the
            // connection and the table, which no two stores share even with the separator left out. Two classes never
            // map one table of an entity manager with the same first field: a subclass inherits the field, and
            // `MappedColumns` leaves it out.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$next\\[\\$table\\]\\[\\$key\\] = is_numeric\\(\\$last\\) \\? \\(int\\) \\$last \\+ 1 : 1;.*',
                    '.*\\$this->take\\(\\$connection, \\$place, \\(int\\) \\$last \\+ 1, false\\);.*',
                    '.*\\$this->take\\(\\$connection, \\$place, \\(int\\) \\$position \\+ \\$offset, true\\);.*',
                    '.*\\$found\\[\\$table\\] = \\(\\$found\\[\\$table\\] \\?\\? 0\\) \\+ \\(int\\) \\$count;.*',
                    '.*\\$rows \\+= \\(int\\) \\$connection->executeStatement\\(.*',
                    '.*Criteria::expr\\(\\)->eq\\(TreeNode::DEPTH_FIELD, \\(int\\) \\$node->treeDepth\\(\\) \\+ 1\\).*',
                    '.*\\$rows\\[\\(int\\) \\$id\\] = \\[.table. => \\$table.*',
                    '.*isset\\(\\$ids\\[\\(int\\) \\$entity->id\\(\\)\\]\\).*',
                ],
            ],
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\\\assert\\(.*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$last = 0;.*',
                    '.*array_fill\\(0, \\$placeholders, \\$who\\).*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_fill\\(0, \\$placeholders, \\$who\\).*',
                ],
            ],
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$ids = array_map\\(static fn \\(mixed \\$id\\): string => .*',
                    '.*\'links\' => array_map\\(array_values\\(\\.\\.\\.\\), \\$links\\).*',
                ],
            ],
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return array_values\\(\\$stores\\);.*',
                ],
            ],
            'Concat' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$key = spl_object_id\\(\\$connection\\)\\.\'\\|\'\\.\\$table;.*',
                ],
            ],
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$key = spl_object_id\\(\\$connection\\)\\.\'\\|\'\\.\\$table;.*',
                ],
            ],
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$tables\\[\\$table\\] \\?\\?= \\[\'columns\' => array_map.*',
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
