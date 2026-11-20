<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    // PHP's own sort direction, which the ORM and the lists take; on PHP 8.5 it comes from symfony/polyfill-php86, in
    // the global namespace.
    'deptrac' => ['allow' => ['^SortDirection$']],
    'docs' => [
        'public' => true,
        // A sale open before the others; what the core's modules sell.
        'words' => ['presale', 'sellables'],
    ],
    'fuzz' => [
        // The price a buyer chose, the value of a line's amount option, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'chosen-price' => 'tests/Fuzz/chosen-price.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
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
                    'src/Domain/Repository/PriceBookRepository.php',
                    'src/Domain/Repository/ProductRepository.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The keys of an array of digits are integers, and the codes of the books come back to text through the
            // callback of `array_map()` that types them: the sorter and the callback read an integer and its text alike,
            // so `strval()` only tells PHPStan the codes are strings.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$seed = array_map\\(strval\\(\\.\\.\\.\\), array_keys\\(\\$byCode\\)\\);',
                ],
            ],
            // The price chosen is minor units written in digits, which the amount option checked when the line was
            // added: PHP adds such a text to an integer as the integer it writes, so the cast only tells PHPStan so.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\$line->unitPrice->minorUnits \\+ \\(int\\) \\$chosen.*'],
            ],
            // SQLite drops the indexes of a column it drops, so only the integration tests on MySQL and PostgreSQL, which
            // compare the table with the mapping, see the index that stays.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['\\$editor->dropIndex\\(\\$index->getObjectName\\(\\)\\);'],
            ],
            // The code is the primary key, a text, and the priority an integer column: the guard and the cast only tell
            // PHPStan so, whatever the driver gives back.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['if \\(\\\\is_string\\(\\$code\\) && is_numeric\\(\\$priority\\)\\) \\{'],
            ],
            // The names of the columns the migration looks for are lowercase, as the table of the numeric model was
            // created: a platform that upper-cases its identifiers is not one the migration reads, `priority` being read
            // by that name below.
            'UnwrapStrToLower' => [
                'ignoreSourceCodeByRegex' => [
                    '.*strtolower\\(\\$column->getObjectName\\(\\)->getIdentifier\\(\\)->getValue\\(\\)\\) === \\$name.*',
                    '\\$columns\\[\\] = strtolower\\(\\$column->getColumnName\\(\\)->getIdentifier\\(\\)->getValue\\(\\)\\);',
                ],
            ],
            // The keys of a decoded JSON object are text or integers, and PHP turns the text of an integer back into one
            // when it keys an array: the cast of the key only tells PHPStan the map is keyed by text.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['\\$map\\[\\(string\\) \\$key\\] = \\(string\\) \\$value;'],
            ],
        ],
    ],
];
