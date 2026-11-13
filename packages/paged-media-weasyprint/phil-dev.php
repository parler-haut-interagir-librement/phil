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
            // The default limits of `LocalAssets` are a policy, documented, not a behaviour; the behaviour of a limit is
            // tested with limits a test can reach.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['int \\$max(File|Total)Bytes = .*']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['int \\$max(File|Total)Bytes = .*']],
            // `file_get_contents()` gives false only when the file disappears between `is_readable()` and the read: the
            // cast keeps the type, and no test can place itself between the two calls.
            'CastString' => ['ignoreSourceCodeByRegex' => ['.*\\(string\\) file_get_contents\\(.*']],
        ],
    ],
    'deptrac' => [
        // The adapter implements the print engine port of phil/paged-media-bundle: it sees the application layer of the
        // bundle, and the rest of the world.
        'ruleset' => [
            'PhilPagedMediaWeasyprint' => ['DefaultAllowed', 'PhilPagedMediaBundleApplication', 'Forbidden'],
        ],
    ],
    'docs' => [
        'public' => true,
        'words' => ['CMYK', 'Pango', 'Weasy', 'WeasyPrint', 'weasyprint'],
    ],
    'fuzz' => [
        // The references of a document may come from anybody: none may reach a file outside the assets directory.
        'targets' => ['assets' => 'tests/Fuzz/assets.php'],
        'budget' => 60,
        'corpus' => 'tests/Fuzz/corpus',
    ],
];
