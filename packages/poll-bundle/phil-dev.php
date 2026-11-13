<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'fuzz' => [
        // The count by Meek's method on ballots nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'meek-count' => 'tests/Fuzz/meek-count.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // ⌈a × b ÷ c⌉ keeps a remainder below c; a remainder equal to c would only move a unit from the quotient to
            // the final rounding up, and a starts at bit 29 because it is at most a billion: both are equivalent mutants.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['for \\(\\$bit = 29; \\$bit >= 0; --\\$bit\\) \\{'],
            ],
            'GreaterThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['if \\(\\$remainder >= \\$c\\) \\{'],
            ],
            // SQLite gives the revision as a number; other drivers may give a numeric string, which the cast turns into one.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['return is_numeric\\(\\$revision\\) \\? \\(int\\) \\$revision : 0;'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside the fuzzer, which gives it what PHPStan does not know.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // The repositories stay in Domain\Repository, as Arkitect requires of the ports of a domain's models.
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Domain/Repository/PollRepository.php',
                    'src/Domain/Repository/VoteRepository.php',
                ],
            ],
        ],
    ],
];
