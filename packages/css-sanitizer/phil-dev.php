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
            // à corriger : complexité cognitive de classe au-dessus de 100, à découper.
            [
                'identifier' => 'complexity.classLike',
                'path' => 'src/Syntax/Tokenizer.php',
                'count' => 1,
            ],
            // à corriger : complexité cognitive au-dessus de 25, à découper : Tokenizer::consumeToken() (30).
            [
                'identifier' => 'complexity.functionLike',
                'path' => 'src/Syntax/Tokenizer.php',
                'count' => 1,
            ],
            // à corriger : déplacer les attributs dans un espace de noms Attribute (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'path' => 'src/Validator/SafeCss.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Syntax/ComponentValue.php',
                    'src/UrlRewriter.php',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The sanitizer is plain PHP; only its constraint, under Validator, sees the rest of the world,
        // symfony/validator.
        'layers' => [
            'PhilCssSanitizer' => '^Phil\\\\CssSanitizer\\\\(?!Validator\\\\).*',
            'PhilCssSanitizerValidator' => '^Phil\\\\CssSanitizer\\\\Validator\\\\.*',
        ],
        'ruleset' => [
            'PhilCssSanitizer' => ['DefaultAllowed'],
            'PhilCssSanitizerValidator' => ['PhilCssSanitizer', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // Each parser on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'css-sanitizer-declarations' => 'tests/Fuzz/css-sanitizer-declarations.php',
            'css-sanitizer-sheet' => 'tests/Fuzz/css-sanitizer-sheet.php',
            'css-sanitizer-nesting' => 'tests/Fuzz/css-sanitizer-nesting.php',
        ],
    ],
];
