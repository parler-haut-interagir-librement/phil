<?php

declare(strict_types=1);

use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\Enums\Meta\Reflection;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The PHPStan extensions the library ships, which every project that installs it loads the same way.
        'includes' => ['extension.neon'],
        'ignoreErrors' => [
            // à corriger : type d’argument trop large relevé par bleedingEdge.
            [
                'identifier' => 'argument.type',
                'path' => 'src/Traits/AsMetadatableEnumTrait.php',
                'count' => 14,
            ],
            // à corriger : new static() dans une classe abstraite, constructeur à rendre final ou fabrique à revoir.
            [
                'identifier' => 'new.staticInAbstractClassStaticMethod',
                'path' => 'src/Meta/AbstractMetaProperty.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The caches of the metadata properties, of the deprecated cases and of the transitions: the attributes do
            // not count their instances and the reflection instantiates nothing, so reading the enum again at each call
            // changes nothing a test can see. The cache of the values stays checked by a property that counts its
            // instances.
            'AssignCoalesce' => [
                'ignore' => [
                    Reflection::class.'::metaProperties',
                    Reflection::class.'::isDeprecated',
                    Reflection::class.'::transitions',
                ],
            ],
            // `Alias` is a property of type `string`; the filter only tells PHPStan, which reads the values of metadata
            // as `mixed`.
            'UnwrapArrayFilter' => [
                'ignore' => [
                    Reflection::class.'::parse',
                ],
            ],
            // The `customMethodName()` hook, which the child overrides to name its accessor: made private, it stays
            // overridable, since `static::customMethodName()` calls the child's protected override from the parent's
            // scope. The custom name stays checked.
            'ProtectedVisibility' => [
                'ignore' => [
                    AbstractMetaProperty::class.'::customMethodName',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The PHPStan extensions are loaded by PHPStan alone: they see the rest of the world, PHPStan and its parser,
        // and the rest of the library sees neither. Allowing them names their layers, which tools in the same
        // workspace may have allowed too.
        'allow' => ['^PHPStan\\\\.*', '^PhpParser\\\\.*'],
        'layers' => [
            'PhilEnums' => '^Phil\\\\Enums\\\\(?!PHPStan\\\\).*',
            'PhilEnumsPhpstan' => '^Phil\\\\Enums\\\\PHPStan\\\\.*',
        ],
        'ruleset' => [
            'PhilEnums' => ['DefaultAllowed'],
            'PhilEnumsPhpstan' => ['PhilEnums', 'DefaultAllowed', 'AllowedPHPStan', 'AllowedPhpParser', 'Forbidden'],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
