<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : complexité cognitive au-dessus de 25, à découper : InlineSanitizer::sanitize() (28).
            [
                'identifier' => 'complexity.functionLike',
                'path' => 'src/Html/InlineSanitizer.php',
                'count' => 1,
            ],
            // à corriger : type des valeurs du fournisseur de données trop étroit.
            [
                'identifier' => 'generator.valueType',
                'path' => 'tests/Unit/Type/TypesTest.php',
                'count' => 1,
            ],
            // à corriger : constructeur d’exception restreint sous le constructeur public d’Exception, à revoir avec les constructeurs nommés.
            [
                'identifier' => 'symplify.parentMethodVisibilityOverride',
                'paths' => [
                    'src/Exception/InvalidDocument.php',
                    'src/Exception/UnknownBlockType.php',
                ],
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Html/BlockRenderer.php',
                    'src/Html/HtmlFilter.php',
                    'src/Schema/Field.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // Whether a tool fits inside the elements open around it, in `InlineTool::fits()`: a tag never opened
            // counts as none open, and `0 < -1` is as false as `0 < 0`.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*static fn \\(string \\$tag\\): bool => 0 < \\(\\$inside\\[\\$tag\\] \\?\\? 0\\)\\);',
                ],
            ],
            // The names of a dictionary, in `Dictionary::clean()`: PHP turns a key of digits into an integer whether
            // it is cast or not, so the cast only tells PHPStan.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$entries\\[\\(string\\) \\$name\\] = \\$text;',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // Each parser on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'ujo' => 'tests/Fuzz/ujo.php',
        ],
    ],
];
