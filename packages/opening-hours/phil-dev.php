<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The day offsets of `OpeningHours::nextOpening()`: exact multiples of 86,400 divided by 86,399 give the
            // same integer under 236 years apart, and are bounded to [0, 366] beyond.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$start = intdiv\\(new DateTimeImmutable\\(\\$special->from, \\$utc\\)->getTimestamp\\(\\) - \\$today, 86_400\\);',
                    '\\$end = \\$special->through === \\$special->from \\? \\$start : intdiv\\(.*, 86_400\\);',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
