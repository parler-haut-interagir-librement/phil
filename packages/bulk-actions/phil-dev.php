<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // Stored runs and selections, filters and bulk edit forms nobody wrote, read back, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'bulk-actions-stored' => 'tests/Fuzz/bulk-actions-stored.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // A filter selection always holds its filter, as only Selection::filter() and fromArray() build one: the throw
            // only tells PHPStan the filter is there.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$selection->filter \\?\\? throw .*',
                ],
            ],
            // Field names start with a letter, so the keys of the values are never numbers PHP would turn into integers;
            // of() casts the names it checks anyway.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\\$set\\[\\(string\\) \\$field\\] = .*'],
            ],
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => ['.*return array_map\\(strval\\(\\.\\.\\.\\), array_keys\\(\\$this->values\\)\\);.*'],
            ],
            // Past the limit, going on instead of leaving the loop adds nothing: the count stays at the limit, the outcome
            // of the rest is not Pending or the limit is reached again.
            'Break_' => [
                'ignoreSourceCodeByRegex' => [
                    '.*break;.*',
                ],
            ],
            // Whether one item is pending or two, the list is empty or it is not.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\\[\\] !== \\$this->pending\\(1\\).*'],
            ],
            // Only the keys of the array are read: what each holds tells nothing.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$unique\\[Check::identifier\\(\\$field, \\$value\\)\\] = true;.*'],
            ],
        ],
    ],
];
