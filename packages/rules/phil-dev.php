<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'fuzz' => [
        // The rule reader on rules nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'rule-reader' => 'tests/Fuzz/rule-reader.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // sprintf() writes an integer as the cast does: the cast only tells PHPStan the shown version is a string.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\\$shown = \\\\is_int\\(\\$version\\) \\? \\(string\\) \\$version : .*'],
            ],
            // A plan is built only by Plan::leaf(), which sets the type and the operands, or by Plan::composite(), which
            // sets neither: its constructor is private, so each of the two tests decides alone and `||` is `&&`.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['.*\\$plan->type instanceof ConditionType && \\$plan->operands instanceof Operands.*'],
            ],
        ],
    ],
];
