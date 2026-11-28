<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // Only the sign of the comparison decides which side moves on: -1 and -2 are both "the audience comes first".
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*!\\$stored->valid\\(\\) => -1,.*'],
            ],
            // Likewise, 1 and 2 are both "the store comes first".
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*!\\$audience->valid\\(\\) => 1,.*'],
            ],
        ],
    ],
];
