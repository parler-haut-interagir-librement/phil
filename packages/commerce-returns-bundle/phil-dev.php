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
            // à corriger : forme de tableau assignée différente du type de la propriété.
            [
                'identifier' => 'assign.propertyType',
                'path' => 'src/Domain/Model/ReturnRequest.php',
                'count' => 2,
            ],
            // à corriger : API dépréciée de Symfony à remplacer.
            [
                'identifier' => 'method.deprecatedInterface',
                'path' => 'tests/Functional/BundleRegistrationTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/Port/Restocking.php',
                    'src/Application/Port/StoreCreditGranter.php',
                    'src/Domain/Repository/ReturnRepository.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // A line with no tax shares none of it: with a tax of -1 instead, the share of its units, between none and
            // all of them, is `intdiv(-2 * units + line units, 2 * line units)`, which truncates to 0.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$tax = \\$taxes\\[\\$line->line\\] \\?\\? 0;',
                    // The discount of a special is never negative, nor are the units, so no share is below 0 and the
                    // greatest of the shares starts from 0 or from -1 alike.
                    '\\$target = 0;',
                ],
            ],
            // The holding returns are only read in turn by the valuation: the keys of the list are never used.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$holding = array_values\\(array_filter\\(.*',
                ],
            ],
            // The lines go down the page by whole lines of 16 points from 570 or 586: the space left is never exactly
            // the 86 points that stay free at the foot, so that `<` and `<=` break the page at the same lines.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$y - \\\\count\\(\\$wrapped\\) \\* self::LEADING < self::MARGIN \\+ 2 \\* self::LEADING\\) \\{',
                ],
            ],
        ],
    ],
];
