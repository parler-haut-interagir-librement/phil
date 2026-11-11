<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The monotonic duration in milliseconds: dividing the nanoseconds by one more or one less than a million
            // changes the whole milliseconds only when the duration falls within a few nanoseconds of a whole millisecond
            // (or after about 1,000 seconds), which the real clock a test reads never sets on purpose.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\(int\\) \\(\\(hrtime\\(true\\) - \\$started\\) / 1_?000_?000\\);',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\(int\\) \\(\\(hrtime\\(true\\) - \\$started\\) / 1_?000_?000\\);',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
