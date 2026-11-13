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
            // à corriger : déplacer les attributs dans un espace de noms Attribute (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'path' => 'src/Application/AsDocumentTemplate.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/DocumentTemplate.php',
                    'src/Application/Port/JobStore.php',
                    'src/Application/Port/PdfStorage.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The bytes of a file held in memory, read by `JobCodec`: `InputFile::chunks()` yields them in one piece,
            // so keeping the last chunk gives the same bytes as joining them all.
            'Assignment' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$contents \\.= \\$chunk;',
                ],
            ],
            // A number read as a float: the return type `float` widens an integer, so the cast changes nothing.
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\\\is_int\\(\\$value\\) \\|\\| \\\\is_float\\(\\$value\\) \\? \\(float\\) \\$value : throw .*',
                ],
            ],
        ],
    ],
];
