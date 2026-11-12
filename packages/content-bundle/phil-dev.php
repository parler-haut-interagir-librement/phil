<?php

declare(strict_types=1);

use Phil\ContentBundle\Infrastructure\Doctrine\DoctrineContentRootRepository;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true, 'words' => ['Doku', 'HMAC', 'savepoint', 'unlocalized']],
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : valeur assignée puis écrasée sans être lue.
            [
                'identifier' => 'assign.overwritten',
                'path' => 'tests/Unit/Infrastructure/Doctrine/ContentStorageTestCase.php',
                'count' => 1,
            ],
            // à corriger : valeur assignée jamais lue.
            [
                'identifier' => 'assign.unused',
                'path' => 'tests/Unit/Application/Publication/PublishedContentsTest.php',
                'count' => 1,
            ],
            // à corriger : complexité cognitive au-dessus de 25, à découper : ContentUrlProvider::provide() (30).
            [
                'identifier' => 'complexity.functionLike',
                'path' => 'src/Infrastructure/Sitemap/ContentUrlProvider.php',
                'count' => 1,
            ],
            // à corriger : API dépréciée de Doctrine à remplacer.
            [
                'identifier' => 'method.deprecated',
                'path' => 'tests/Unit/Infrastructure/Doctrine/ContentStorageTestCase.php',
                'count' => 4,
            ],
            // à corriger : API interne de Doctrine à remplacer par une API publique.
            [
                'identifier' => 'method.internalClass',
                'path' => 'tests/Unit/Infrastructure/Doctrine/ContentStorageTestCase.php',
                'count' => 2,
            ],
            // à corriger : déplacer les exceptions dans un espace de noms Exception (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireExceptionNamespace',
                'paths' => [
                    'src/Infrastructure/Doctrine/InvalidContentMapping.php',
                    'tests/Unit/Infrastructure/Doctrine/Fixture/InvalidLabelCode.php',
                ],
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/Content/ContentRootFactory.php',
                    'src/Application/Persistence/ContentTransaction.php',
                    'src/Application/Pipeline/DataMapper.php',
                    'src/Application/Pipeline/Merger.php',
                    'src/Application/Pipeline/Normalizer.php',
                    'src/Application/Publication/BodyConverter.php',
                    'src/Domain/Capability/BodyCapable.php',
                    'src/Domain/Capability/ExcerptCapable.php',
                    'src/Domain/Capability/LinkCapable.php',
                    'src/Domain/Capability/RoutableCapable.php',
                    'src/Domain/Capability/SeoCapable.php',
                    'src/Domain/Capability/TemplateCapable.php',
                    'src/Domain/Capability/WorkflowCapable.php',
                    'src/Domain/Content/ContentRoot.php',
                    'src/Domain/Content/DimensionContent.php',
                    'src/Domain/Repository/ContentRootRepository.php',
                    'src/Domain/Repository/DimensionContentRepository.php',
                    'src/Domain/Repository/PublishedRowRepository.php',
                    'src/Domain/Repository/RouteRepository.php',
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
                'src/PhilContentBundle.php',
                'tests/Unit/PhilContentBundleTest.php',
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The integer identifier of a root that `DoctrineContentRootRepository` rebuilds from a reference: Doctrine
            // finds it the same way in its string form, so removing the cast changes nothing a test can see. Only this
            // removal is excused: the check of the digits stays mutated.
            'CastInt' => [
                'ignore' => [
                    DoctrineContentRootRepository::class.'::identifier',
                ],
            ],
            // The short circuit of an empty page in `DoctrinePublishedRowRepository::findPublished()`: without it, the
            // query of the shared rows runs on an empty list and the result stays empty. It only spares a query, which
            // no test sees.
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\$localized;',
                ],
            ],
            // The lists of `DoctrinePublishedRowRepository` bound as array parameters — the identifiers asked for, the
            // identifiers of the roots found, the versions found — have integer keys, and DBAL expands an array
            // parameter by its values in order whatever its keys: `array_values()` only gives them their list type.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$identifiers = null === \\$query->resourceIds \\? null : array_values\\(array_filter\\(.*',
                    '\\$identifiers = array_map\\(static fn \\(object \\$root\\): mixed => array_first\\(\\$metadata->getIdentifierValues\\(\\$root\\)\\), array_values\\(\\$roots\\)\\);',
                    '->setParameter\\(\'versions\', array_values\\(\\$versions\\), ArrayParameterType::INTEGER\\)',
                ],
            ],
            // See `ReturnRemoval`.
            'ArrayOneItem' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\$localized;',
                ],
            ],
            // The order of the dimension rows of `DoctrineDimensionContentRepository::findAll()`: SQLite, MySQL and
            // PostgreSQL all return the rows in the order of the unique index, which ends with `locale_key`, so
            // removing the sort changes nothing a test can see. The order stays checked; only its removal is excused.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\[\'localeKey\' => \'ASC\'\\],',
                ],
            ],
        ],
    ],
];
