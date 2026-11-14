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
            // A value in a condition: the strict rules of PHPStan want the cast that the ternary makes on its own.
            'CastBool' => [
                'ignoreSourceCodeByRegex' => [
                    'ColumnType::Boolean => \\(bool\\) \\$value \\? \'1\' : \'0\',',
                    'ColumnType::Boolean === \\$column->type => \\(bool\\) \\$value \\? \\$this->yes : \\$this->no,',
                ],
            ],
            // `is_numeric()` has passed: adding or taking away zero only turns the numeric string into a number.
            'Plus' => [
                'ignoreSourceCodeByRegex' => [
                    'return 0 === \\$column->decimals \\? \\(int\\) \\$raw : \\$raw \\+ 0;',
                ],
            ],
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    // Only a sortable column reaches this line: the field is never null, the cast serves PHPStan.
                    '\\$field = \\(string\\) \\$column->sortField;',
                    // Symfony's validator gives a string; the cast covers the Stringable its interface also allows.
                    '\\$errors\\[\\] = \\(string\\) \\$violation->getMessage\\(\\);',
                ],
            ],
            // Two equal days swapped stay the same two days.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$until instanceof DateTimeImmutable && \\$from > \\$until.*',
                ],
            ],
            // The memos of one render: formatters and translated choices built again give the same text, only the
            // cost changes.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$formatter = \\$this->numbers\\[\\$column->name\\] \\?\\?= .*',
                    '\\$this->dates\\[\\$column->name\\] \\?\\?= .*',
                    'return \\$this->choices\\[\\$column->name\\]\\[\\$value\\] \\?\\?= .*',
                ],
            ],
            // Whether the paginator treats the query as fetch-joining a collection changes the SQL it writes, not the
            // rows it returns for a query that joins none.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    'private bool \\$fetchJoinCollection = false,',
                ],
            ],
        ],
    ],
];
