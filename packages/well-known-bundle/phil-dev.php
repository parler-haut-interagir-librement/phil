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
    ],
    'infection' => [
        'mutators' => [
            // The year of a full-date read from its first group: the whole match, "2026-11-21", is read by `(int)` up to the
            // first character that is not a digit, which is the same year.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*checkdate\\(\\(int\\) \\$parts\\[2\\], \\(int\\) \\$parts\\[3\\], \\(int\\) \\$parts\\[1\\]\\).*'],
            ],
            // The constructor of Symfony's Loader only sets the environment, which the route loader never reads: without
            // the call, no route and no answer differ.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['parent::__construct\\(\\);'],
            ],
        ],
    ],
    'fuzz' => [
        // The reader of WebFinger queries and their resources, from the seeds of tests/Fuzz/corpus/webfinger-query.
        'targets' => [
            'webfinger-query' => 'tests/Fuzz/webfinger-query.php',
        ],
    ],
    'docs' => [
        // The package is released from its own repository: its CHANGELOG uses every section.
        'public' => true,
        // The protocols and formats the documents speak.
        'words' => ['WebFinger', 'NodeInfo', 'atproto', 'assetlinks', 'openpgp4fpr', 'Halfyear'],
    ],
];
