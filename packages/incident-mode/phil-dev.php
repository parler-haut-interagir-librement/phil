<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'infection' => [
        'mutators' => [
            // The driver gives the number of rows changed as an integer, or as a numeric string that the cast turns into
            // the same integer: the cast only serves the strict comparison with 1.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['return 1 === \\(int\\) \\$this->connection->executeStatement\\(.*'],
            ],
            // The identifiers read from the column are strings: the filter only serves the type of the signature.
            'UnwrapArrayFilter' => [
                'ignoreSourceCodeByRegex' => ['return array_values\\(array_filter\\(\\$this->connection->fetchFirstColumn\\(\\$sql, \\[\\$now\\].*'],
            ],
            // A product outside the bounds, whatever its value, is refused by the bounds: the minimum is 60 seconds.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['return \\$count > intdiv\\(\\\\PHP_INT_MAX, \\$unit\\).*'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['return \\$count > intdiv\\(\\\\PHP_INT_MAX, \\$unit\\).*'],
            ],
            // A control character, newline included, is refused before the pattern is tried, so that `$` without `D`
            // never meets a trailing newline.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['if \\(1 === preg_match\\(./\\^\\[\\^@\\]\\+@.*'],
            ],
            // `usort` and `array_slice` renumber the keys before the list is returned.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$all = array_values\\(\\$this->activations\\);',
                    // The filter keeps every identifier, so that the list stays the one the driver gave.
                    'return array_values\\(array_filter\\(\\$this->connection->fetchFirstColumn\\(\\$sql, \\[\\$now\\].*',
                ],
            ],
            // The column of the primary key is not null whatever it is declared.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['->addColumn\\(self::column\\(.id., Types::STRING, Activation::ID_LENGTH, true\\)->edit.*'],
            ],
        ],
    ],
    'deptrac' => [
        // The core is plain PHP; the DBAL store and the Messenger lifting are adapters, which alone see Doctrine and the
        // message bus.
        'layers' => [
            'PhilIncidentMode' => '^Phil\\\\IncidentMode\\\\(?!(Dbal|Messenger)\\\\).*',
            'PhilIncidentModeAdapter' => '^Phil\\\\IncidentMode\\\\(Dbal|Messenger)\\\\.*',
        ],
        'ruleset' => [
            'PhilIncidentMode' => ['DefaultAllowed'],
            'PhilIncidentModeAdapter' => ['PhilIncidentMode', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
];
