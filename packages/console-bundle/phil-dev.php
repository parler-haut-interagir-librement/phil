<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    // The Monolog handler's parent constructor sets the level and the bubbling to the defaults its
                    // properties already hold; without the call, the handler behaves the same.
                    'parent::__construct\(\);',
                ],
            ],
            'Assignment' => [
                'ignoreSourceCodeByRegex' => [
                    // A filter of a write stream gets one bucket per write, and $consumed starts at zero for each call:
                    // adding the bucket's length or assigning it gives the same count.
                    '\$consumed \+= \$bucket->datalen;',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
