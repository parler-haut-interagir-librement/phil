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
            // à corriger : type d’argument trop large relevé par bleedingEdge.
            [
                'identifier' => 'argument.type',
                'path' => 'src/Metadata/MetadataImporter.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The memos of the index of the regions and of the placements: computing them again gives the same result,
            // only the cost changes.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    'self::\\$known \\?\\?= array_fill_keys\\(self::regions\\(\\), true\\);',
                    'self::\\$placements \\?\\?= new WeakMap\\(\\);',
                    '(?:return self::\\$placements\\[\\$this\\] = \\$this->place\\(\\);|return self::\\$placements\\[\\$this\\] \\?\\?= \\$this->place\\(\\);)',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
