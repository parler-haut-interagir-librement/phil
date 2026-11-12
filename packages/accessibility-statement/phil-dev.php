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
    'fuzz' => [
        // The readers of a statement and of a report on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'statement-reader' => 'tests/Fuzz/statement-reader.php',
            'report-reader' => 'tests/Fuzz/report-reader.php',
        ],
    ],
    'docs' => [
        // The package is released from its own repository: its CHANGELOG uses every section.
        'public' => true,
        // The Web Content Accessibility Guidelines, by the name the W3C gives them.
        'words' => ['WCAG'],
    ],
];
