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
        // The protocols and formats the documents speak.
        'words' => ['WebFinger', 'NodeInfo', 'atproto', 'assetlinks', 'openpgp4fpr', 'Halfyear'],
    ],
];
