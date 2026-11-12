<?php

declare(strict_types=1);

use Phil\DataTableExport\Xlsx\XlsxWriter;

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
    'infection' => [
        'mutators' => [
            // A handle is closed when the function that opened it ends, by a return or an exception: closing it in a `finally` says so, and no test can tell the two apart.
            'UnwrapFinally' => ['ignore' => [XlsxWriter::class.'::worksheet', XlsxWriter::class.'::copy']],
        ],
    ],
    'fuzz' => [
        // The text of a cell, written by both writers: well-formed XML, and never a formula in CSV.
        'targets' => [
            'cell' => 'tests/Fuzz/cell.php',
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
