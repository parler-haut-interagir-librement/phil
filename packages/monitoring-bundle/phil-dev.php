<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The two formats are digit strings: without the casts, PHP subtracts the numeric strings to the same
            // integer. The casts keep the arithmetic on integers for PHPStan.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['return max\\(0, intdiv\\(\\(int\\) \\$this->clock->now\\(\\)->format\\(\'Uu\'\\) - \\(int\\) \\$start->format\\(\'Uu\'\\), 1000\\)\\);'],
            ],
            // The stat cache of PHP goes stale only when another process creates or removes the file or directory:
            // the functions of PHP that change a file clear it themselves, so no test of one process sees the
            // difference. A long-running worker does.
            'FunctionCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['clearstatcache\\(true, \\$this->(directory|path)\\);'],
            ],
        ],
    ],
    'docs' => [
        // The key-value server and the storage library the checks reach.
        'words' => ['Valkey', 'Flysystem'],
    ],
];
