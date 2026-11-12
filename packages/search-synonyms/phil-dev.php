<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The readers of the files and documents an administrator uploads, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'solr-synonyms' => 'tests/Fuzz/solr-synonyms.php',
            'json-synonyms' => 'tests/Fuzz/json-synonyms.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // A file one byte longer than the limit is refused as a file two bytes longer is: the reader only needs to see
            // that the limit is passed.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$text = file_get_contents\\(\\$file, length: SolrSynonymsReader::MAX_BYTES \\+ 1\\);',
                    // The name of a file beside the one being written is a random number: `0` or `1` as its lowest changes nothing.
                    '\\$next = \\\\sprintf\\(.*random_int\\(0, .*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['\\$next = \\\\sprintf\\(.*random_int\\(0, .*'],
            ],
            // `mkdir()` fails and the directory exists only when another process creates it between the two calls: no test
            // can make the second operand matter, and `||` and `&&` cannot be told apart without it.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['if \\(file_exists\\(\\$this->directory\\) \\? .*mkdir\\(.*'],
            ],
        ],
    ],
    'docs' => [
        // The package is released from its own repository: its CHANGELOG uses every section.
        'public' => true,
        'translations' => 'blocking',
        // The primary part of a language tag, `fr` in `fr-CA`.
        'words' => ['subtag'],
    ],
];
