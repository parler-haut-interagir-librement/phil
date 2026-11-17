<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'deptrac' => [
        // Two layers: the outbox, plain PHP on the PSR interfaces; and src/Test, the contract tests published for the
        // stores of other packages, which is test code and sees PHPUnit, a development requirement.
        'layers' => [
            'PhilOutbox' => '^Phil\\\\Outbox\\\\(?!Test\\\\).*',
            'PhilOutboxTest' => '^Phil\\\\Outbox\\\\Test\\\\.*',
        ],
        'ruleset' => [
            'PhilOutbox' => ['DefaultAllowed'],
            'PhilOutboxTest' => ['PhilOutbox', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'infection' => [
        // src/Test is the contract the stores of other packages run, test code that no mutant of it can be told
        // apart from by the tests of the package: its own mutants are assertions weakened, which only a broken store
        // run against it shows.
        'excludes' => ['Test'],
        'mutators' => [
            // The records are kept by the position they were added at, from 0 or from 1 alike.
            'Increment' => ['ignoreSourceCodeByRegex' => ['.*\\$this->records\\[\\+\\+\\$this->position\\] = \\$record;.*']],
            // A loop that stops at its limit by `break` or by `continue` (which meets the limit again at the next
            // record) leaves the same records.
            'Break_' => ['ignoreSourceCodeByRegex' => ['.*break;.*']],
            // `isset` is true for a key kept as false.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['.*\\$held\\[\\$key\\] = true;.*']],
            // The offset is clamped to 0 below; `array_slice` renumbers the keys that `array_values` renumbered.
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*int \\$offset = 0\\): array.*']],
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => ['.*array_slice\\(array_values\\(array_filter.*']],
        ],
    ],
    'fuzz' => [
        // The reader of the payloads a store kept, on bytes nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'payload' => 'tests/Fuzz/payload.php',
        ],
    ],
];
