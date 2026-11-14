<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // The memo of the values decoded within a request: decoded again, a value is the same, only the cost changes.
            'LogicalNot' => ['ignoreSourceCodeByRegex' => ['if \\(isset\\(\\$this->read\\[\\$subjectKey\\]\\[\\$definition->key\\]\\)\\) \\{']],
            // The rows of the database are strings, as their columns are: the assertion only tells PHPStan so, and no row can make one of its conditions differ.
            'LogicalAnd' => ['ignoreSourceCodeByRegex' => ['\\\\assert\\(\\\\is_string\\(\\$scope\\) && \\\\is_string\\(\\$subject\\) && \\\\is_string\\(\\$value\\)\\);']],
            // A name that is not declared is passed over by the ordering, as an item that is not a string is: a list filtered of them, or not, or not reindexed, orders the same.
            'UnwrapArrayFilter' => ['ignoreSourceCodeByRegex' => ['return \\\\is_array\\(\\$versions\\) \\? array_values\\(array_filter\\(\\$versions, \\\\is_string\\(\\.\\.\\.\\)\\)\\) : \\[\\];']],
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => ['return \\\\is_array\\(\\$versions\\) \\? array_values\\(array_filter\\(\\$versions, \\\\is_string\\(\\.\\.\\.\\)\\)\\) : \\[\\];']],
            'ReturnRemoval' => ['ignoreSourceCodeByRegex' => ['return \\$this->read\\[\\$subjectKey\\]\\[\\$definition->key\\];']],
        ],
    ],
    'fuzz' => [
        // The reader of the preference cookie, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'cookie' => 'tests/Fuzz/cookie.php',
        ],
    ],
];
