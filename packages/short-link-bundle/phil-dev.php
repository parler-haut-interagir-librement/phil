<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    // PHP's own sort direction, which the ORM takes; on PHP 8.5 it comes from symfony/polyfill-php86, in the global
    // namespace that the default layers do not cover.
    'deptrac' => ['allow' => ['^SortDirection$']],
    'fuzz' => [
        // The identifier codec and the classifier of visits on inputs nobody wrote, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'sqid-decode' => 'tests/Fuzz/sqid-decode.php',
            'visitor-classify' => 'tests/Fuzz/visitor-classify.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // Bundle::build() is empty: the parent call only keeps the chain for a bundle that would one day fill it.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*parent::build\\(\\$container\\);.*'],
            ],
            // The tree of the configuration gives every key of these sections a value, and `canBeDisabled()` its
            // `enabled`: the fallback of a missing key only tells PHPStan so, and no configuration reaches it.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$rate\\[.enabled.\\] \\?\\? false.*',
                    '.*\\$rate\\[.single_server.\\] \\?\\? false.*',
                    '.*\\$admin\\[.enabled.\\] \\?\\? false.*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$rate\\[.limit.\\] \\?\\? 30.*',
                    // A DQL COUNT is a number on every driver: the fallback only tells PHPStan so.
                    '.*is_numeric\\(\\$count\\) \\? \\(int\\) \\$count : 0.*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$rate\\[.limit.\\] \\?\\? 30.*',
                    '.*is_numeric\\(\\$count\\) \\? \\(int\\) \\$count : 0.*',
                    // An identifier of the codec has a prefix and at least one character per number: a minimum length
                    // of 1 pads nothing more than a minimum of 0.
                    '.*private int \\$minLength = 0, iterable \\$blocklist.*',
                ],
            ],
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*is_numeric\\(\\$count\\) \\? \\(int\\) \\$count : 0.*',
                    // The codec looks up only characters it checked are in the alphabet: strpos() never answers false.
                    '.*\\(int\\) strpos\\(\\$(this->)?alphabet, .*',
                ],
            ],
            // The words of the blocklist are only walked through: their keys are never read.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->blocklist = array_values\\(\\$words\\);.*'],
            ],
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    // Shuffling the alphabet after the last number changes nothing: the loop ends there.
                    '.*if \\(\\\\count\\(\\$chunks\\) > 1\\) \\{.*',
                    // An increment equal to the length of the alphabet gives the offset of increment 0, already blocked.
                    '.*if \\(\\$increment > \\$length\\) \\{.*',
                ],
            ],
            // A text of three characters only meets words of three characters or fewer, the longer ones skipped above.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['.*if \\(\\$length <= 3 \\|\\| .*'],
            ],
            // Whether Twig, the form component and the CSRF component are installed: the development environment has
            // all three, so only the flag the tests pass decides.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['.*!class_exists\\(Environment::class\\) \\|\\| !interface_exists.*'],
            ],
            // A request on a domain that the resolver handles always gets an answer from it: the listener asks
            // `handles()` first, and `resolve()` answers `null` for the same hosts only.
            'NullSafePropertyCall' => [
                'ignoreSourceCodeByRegex' => ['.*410 === \\$resolution\\?->status.*'],
            ],
            // The conditions of the one statement that counts a visit are joined by AND, in any order.
            'Concat' => [
                'ignoreSourceCodeByRegex' => [
                    '.*WHERE id = :id AND enabled = :enabled.*',
                    '.*AND .valid_from IS NULL OR valid_from <= :now.*',
                ],
            ],
            'ConcatOperandRemoval' => [
                // The source of the rule only names it: the location is built from the target and the query.
                'ignoreSourceCodeByRegex' => ['.*SourcePattern::exact\\(.*'],
            ],
            // The identifier is bound as an integer for the drivers that refuse a text for a number; SQLite, of the unit
            // tests, compares by affinity, and the integration suites on MySQL and PostgreSQL take the binding.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*.id. => ParameterType::INTEGER.*'],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['Sqids']],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
