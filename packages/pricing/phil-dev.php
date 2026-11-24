<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['MSRP', 'PDSF', 'roundings', 'réaffiche', 'réaffichés']],
    'infection' => [
        'mutators' => [
            // Equivalent: IntegerMath::divide() reads the sign of a denominator that left a remainder, so never 1 or
            // -1; comparing it with 1 or -1 instead of 0 gives the same sign.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['\$denominatorSign = \$denominator <=> 0;']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['\$denominatorSign = \$denominator <=> 0;']],
        ],
        // The property tests run thousands of generated cases: a mutant of the shared arithmetic replays them all.
        'timeout' => 30,
    ],
];
