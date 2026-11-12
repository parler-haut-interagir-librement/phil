<?php

declare(strict_types=1);

use Phil\AnalyticsBundle\Application\Collection\Collector;
use Phil\AnalyticsBundle\Infrastructure\Doctrine\DbalCounterStore;
use Phil\AnalyticsBundle\Infrastructure\Salt\CacheSaltStore;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The payload is only read by name: a numeric key of a JSON list never matches one. The filter only serves
            // the array<string, mixed> of the signature.
            'UnwrapArrayFilter' => [
                'ignore' => [Collector::class.'::payloadOf'],
            ],
            // An array key is an integer or a string whatever the cast: the cast only serves the array<string, int>
            // of the signature.
            'CastString' => [
                'ignore' => [DbalCounterStore::class.'::integers'],
            ],
            // A Symfony lock releases itself when it goes out of scope, at the return of the claim or on an exception:
            // the explicit release only says when.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['\\$lock\\?->release\\(\\);'],
            ],
            'UnwrapFinally' => [
                'ignore' => [CacheSaltStore::class.'::claim'],
            ],
        ],
    ],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API dépréciée de Symfony à remplacer.
            [
                'identifier' => 'method.deprecatedInterface',
                'path' => 'tests/Functional/BundleRegistrationTest.php',
                'count' => 1,
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
