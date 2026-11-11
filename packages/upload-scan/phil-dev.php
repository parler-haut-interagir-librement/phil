<?php

declare(strict_types=1);

use Phil\UploadScan\Clamd\ClamdScanner;
use Phil\UploadScan\FakeScanner;

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
            // The code inside each `try` cannot throw — the scan answers an error result for every failure, and the
            // stream was checked before —: closing in `finally` or after it is the same.
            'UnwrapFinally' => [
                'ignore' => [
                    ClamdScanner::class.'::scanStream',
                    ClamdScanner::class.'::scanPath',
                    ClamdScanner::class.'::command',
                    FakeScanner::class.'::scanPath',
                ],
            ],
            // A microsecond more or less of a timeout is no difference a test can see.
            'IncrementInteger' => ['ignore' => [ClamdScanner::class.'::limit']],
            'DecrementInteger' => ['ignore' => [ClamdScanner::class.'::limit']],
            // On a socket, a read of more than what is left of the limit returns what has arrived, and the loop stops
            // at the limit either way; the read that differs is never reached by an answer that arrives at once.
            'Minus' => ['ignore' => [ClamdScanner::class.'::read']],
        ],
    ],
    'fuzz' => [
        // The reader of clamd's answers on bytes nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'clamd-response' => 'tests/Fuzz/clamd-response.php',
        ],
    ],
    'docs' => [
        // The daemon of ClamAV and the test file every antivirus reports, as their documentation spells them.
        'words' => ['clamd', 'EICAR'],
    ],
];
