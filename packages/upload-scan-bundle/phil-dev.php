<?php

declare(strict_types=1);

use Phil\UploadScanBundle\Application\RescanFileHandler;
use Phil\UploadScanBundle\Application\UploadScanner;
use Phil\UploadScanBundle\Infrastructure\Quarantine\FilesystemQuarantine;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // The constraint sits beside its validator, as Symfony pairs a Constraint and its ConstraintValidator and as
            // phil/media-bundle places MediaFile: an Attribute namespace would split the pair.
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'path' => 'src/Infrastructure/Validator/CleanFile.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The copy inside the first `try` cannot throw: the stream was checked before, and the warnings are silenced.
            // In the others, a stream is closed by PHP as soon as its last reference goes: an exception that skips the
            // `fclose()` leaves nothing open, so closing in `finally` or after it cannot be told apart.
            'UnwrapFinally' => [
                'ignore' => [
                    FilesystemQuarantine::class.'::isolate',
                    UploadScanner::class.'::inspectPath',
                    RescanFileHandler::class.'::__invoke',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // The reader of the quarantine's records and of the identifiers of the administration's requests, from the
        // seeds of tests/Fuzz/corpus/<target>.
        'targets' => ['quarantine-record' => 'tests/Fuzz/quarantine-record.php'],
        'budget' => 60,
    ],
    'docs' => [
        'public' => true,
        // The daemon of ClamAV and the test file every antivirus reports, as their documentation spells them.
        'words' => ['clamd', 'EICAR', 'seekable', 'unscanned'],
    ],
];
