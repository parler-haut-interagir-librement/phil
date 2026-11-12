<?php

declare(strict_types=1);

use Phil\Idempotency\IdempotencyDecision;
use Phil\Idempotency\IdempotencyKey;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither. The child
        // script of the concurrency test runs in its own process, outside the autoloaded suite.
        'excludePaths' => ['tests/Fuzz/*', 'tests/Integration/race.php'],
    ],
    'deptrac' => [
        // The core is plain PHP; the stores and the Messenger middleware are adapters, which alone see Doctrine DBAL and
        // the optional components.
        'layers' => [
            'PhilIdempotency' => '^Phil\\\\Idempotency\\\\(?!(Store|Messenger)\\\\).*',
            'PhilIdempotencyAdapter' => '^Phil\\\\Idempotency\\\\(Store|Messenger)\\\\.*',
        ],
        'ruleset' => [
            'PhilIdempotency' => ['DefaultAllowed'],
            'PhilIdempotencyAdapter' => ['PhilIdempotency', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'infection' => [
        'mutators' => [
            // A decision is `Replay` only on a completed record: `existing` is never null there, and the nullsafe call
            // only serves the `?IdempotencyRecord` of the property.
            'NullSafePropertyCall' => [
                'ignore' => [IdempotencyDecision::class.'::replay'],
            ],
            // Without `D`, `$` also matches before a final newline; the `trim()` beside it refuses that newline anyway.
            'PregMatchRemoveFlags' => [
                'ignore' => [IdempotencyKey::class.'::fromString'],
            ],
        ],
    ],
    'fuzz' => [
        // The reader of the Idempotency-Key header, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'idempotency-key' => 'tests/Fuzz/idempotency-key.php',
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
