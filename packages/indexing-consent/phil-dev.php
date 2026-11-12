<?php

declare(strict_types=1);

use Phil\IndexingConsent\Indexing;
use Phil\IndexingConsent\IndexingPolicy;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The values a reader allows are always the first cases, from the widest: their keys are already 0, 1…, and
            // array_values only serves the list of the signature. Records named by their argument keep their keys
            // through array_map, and a spread of named records fills the variadic of strictest() alike.
            'UnwrapArrayValues' => [
                'ignore' => [Indexing::class.'::allowing', IndexingPolicy::class.'::ofAll'],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
