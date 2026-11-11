<?php

declare(strict_types=1);

use Phil\RouteAccessCheckBundle\Infrastructure\Console\CheckRouteAccessCommand;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The baseline file is read one byte past the library's limit, so that a larger file is refused without
            // being read whole; reading two bytes past it gives the same refusal.
            'IncrementInteger' => [
                'ignore' => [CheckRouteAccessCommand::class.'::readBaseline'],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
