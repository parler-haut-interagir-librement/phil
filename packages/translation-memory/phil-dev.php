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
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'path' => 'src/Memory/MemoryStore.php',
                'count' => 1,
            ],
        ],
    ],
    'fuzz' => [
        // The readers of TMX and TBX files and the plural check on inputs nobody wrote, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'tmx-reader' => 'tests/Fuzz/tmx-reader.php',
            'tbx-reader' => 'tests/Fuzz/tbx-reader.php',
            'plurals' => 'tests/Fuzz/plurals.php',
        ],
    ],
    'docs' => [
        // The package is released from its own repository: its CHANGELOG uses every section.
        'public' => true,
        // The Unicode Common Locale Data Repository, by its initials, and the parts of a language tag.
        'words' => ['CLDR', 'subtag', 'subtags'],
    ],
    'infection' => [
        'mutators' => [
            // The choice of the computation of the Levenshtein distance, in `Similarity::distance()`: two texts of
            // exactly 256 different characters are written on one byte each, and the table computed in PHP gives the
            // same distance as `levenshtein()`; only the cost changes.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\\\count\\(\\$symbols\\) > 256\\) \\{',
                ],
            ],
            // The tags counted in a text are at least one: a count of 0 or -1 for a tag the other text lacks is below it
            // all the same.
            //
            // The search of the character map of a font, in `Glyphs::covers()`, halves the runs left to read; a step of one
            // reads them one by one from the highest, and finds the same run: the choice is the speed of the search.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$middle = intdiv\\(\\$low \\+ \\$high, 2\\);',
                    'if \\(\\(\\$(?:target|source)\\[\\$tag\\] \\?\\? 0\\) < \\$count\\) \\{',
                ],
            ],
            // The closing apostrophe of a quoted brace is searched from the character after the apostrophe: that character
            // is the brace, so a search from the one after it finds the same apostrophe.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$end = strpos\\(\\$this->text, "\'", \\$this->at \\+ 1\\);',
                ],
            ],
        ],
    ],
];
