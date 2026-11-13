<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither. The racer of
        // the concurrency test runs in its own process, outside the autoloaded suite.
        'excludePaths' => ['tests/Fuzz/*', 'tests/Integration/race.php'],
    ],
    'deptrac' => [
        // The core is plain PHP; the DBAL store is the adapter, which alone sees Doctrine DBAL.
        'layers' => [
            'PhilAccessCodes' => '^Phil\\\\AccessCodes\\\\(?!Dbal\\\\).*',
            'PhilAccessCodesAdapter' => '^Phil\\\\AccessCodes\\\\Dbal\\\\.*',
        ],
        'ruleset' => [
            'PhilAccessCodes' => ['DefaultAllowed', 'PhilUrl'],
            'PhilAccessCodesAdapter' => ['PhilAccessCodes', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // The reader of typed codes, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'code-format' => 'tests/Fuzz/code-format.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The value of a key that only collects distinct codes: `array_keys()` reads the keys and never the value.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$codes\\[\\$this->format->generate\\(\\$this->randomizer\\)\\] = true;.*'],
            ],
            // The statement carries as many placeholders as the chunk has rows: `implode()` joins the values of the
            // filled array and never reads its keys, wherever they start.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*array_fill\\(0, \\\\count\\(\\$chunk\\).*'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*array_fill\\(0, \\\\count\\(\\$chunk\\).*'],
            ],
            // A single parameter of a statement is bound as text when its type is left out, which is the type it is
            // given: `[ParameterType::STRING]` and `[]` bind the same.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*\\[ParameterType::STRING\\],$'],
            ],
            // A redemption that changed a row finds that row again, in the same connection and a moment later: the
            // code of the batch is there, and no test can take the row away between the two statements.
            'NullSafePropertyCall' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->find\\(\\$candidates\\)\\?->batchId.*'],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['HMAC']],
];
