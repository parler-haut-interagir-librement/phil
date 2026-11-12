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
    'deptrac' => [
        // The core of PHP outside the default: the HTML5 parser of the DOM extension reads the rendered body, so that a
        // value is filled into its text only.
        'ruleset' => [
            'PhilLegalNotices' => ['DefaultAllowed', 'Forbidden'],
        ],
    ],
    'docs' => [
        'public' => true,
        // The wiki format named where the doc lists the markers each format reserves.
        'words' => ['DokuWiki'],
    ],
    'fuzz' => [
        // The placeholders and the durations on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'placeholders' => 'tests/Fuzz/placeholders.php',
            'durations' => 'tests/Fuzz/durations.php',
        ],
    ],
];
