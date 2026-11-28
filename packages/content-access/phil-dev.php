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
        // The policy reader on policies nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'policy-reader' => 'tests/Fuzz/policy-reader.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The constructor is private and its callers build a rule exactly for a restricted audience, so neither
            // guard of the audience and the rule can fire: they hold the invariant should a factory ever change.
            'LogicalAndAllSubExprNegation' => [
                'ignoreSourceCodeByRegex' => [
                    '.*Audience::Restricted === \\$audience && !\\$rule instanceof Rule.*',
                    '.*Audience::Restricted !== \\$audience && \\$rule instanceof Rule.*',
                ],
            ],
            // Days are clamped to zero by Grant::daysAfterStart(): a default of -1 opens at the same instant as 0.
            // A JSON depth of 511 or 513 for the rule's own canonical form cannot be told apart: a rule is at most
            // 64 levels deep, far under both.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$operands->has\\(\'after_days\'\\) \\? \\$operands->int\\(\'after_days\'\\) : 0;.*',
                    '.*json_decode\\(\\$this->rules->toJson\\(\\$policy->rule\\), false, 512,.*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*json_decode\\(\\$this->rules->toJson\\(\\$policy->rule\\), false, 512,.*',
                ],
            ],
            // Both sides of the comparison hold the same instant when they are equal: which one is kept does not show.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['.*\\$opens < \\$opensAt \\? \\$opens : \\$opensAt.*'],
            ],
            // The prefix is made of digits only by the pattern before it: PHP compares such a string as the number.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\(int\\) \\$parts\\[1\\] <= \\$bits.*'],
            ],
            // A private list that no member reads by position: a source given by name keeps its key, unseen.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->sources = array_values\\(\\$sources\\);.*'],
            ],
            // sprintf("%s") writes an integer as (string) does, and preg_replace() on this pattern never returns null.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$shown = \\\\is_int\\(\\$version\\) \\? \\(string\\) \\$version.*',
                    '.*\\$clean = \\(string\\) preg_replace.*',
                ],
            ],
            // The format sets every field of the instant, so the reset of the others changes nothing, and a fallback
            // is no DateTimeImmutable whatever its value: the check after it throws.
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*createFromFormat\\(\'!\'\\.self::INSTANT_FORMAT.*'],
            ],
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*createFromFormat\\(\'!\'\\.self::INSTANT_FORMAT.*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
