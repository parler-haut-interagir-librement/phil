<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The core is plain PHP on Symfony components; the DBAL stores alone see Doctrine, an optional dependency,
        // required in development and suggested.
        'layers' => [
            'PhilAutomations' => '^Phil\\\\Automations\\\\(?!Dbal\\\\).*',
            'PhilAutomationsDbal' => '^Phil\\\\Automations\\\\Dbal\\\\.*',
        ],
        'ruleset' => [
            'PhilAutomations' => ['DefaultAllowed', 'PhilStandardWebhooks', 'PhilUrl'],
            'PhilAutomationsDbal' => ['PhilAutomations', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // The definition reader on JSON nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'definition-reader' => 'tests/Fuzz/definition-reader.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // `allSteps()` reads a definition the constructor has already checked at depth 1: starting lower, or
            // reading the `else` list one level up, finds no definition the `then` list and the constructor let
            // through, in the reader as in the definition; `first()` is `StepPath::top()`, which holds its argument
            // at 0.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'return self::collect\\(\\$this->steps, \\$ids, 1\\);',
                    'return StepPath::top\\(0\\);',
                    'array_push\\(\\$all, \\.\\.\\.self::collect\\(\\$step->then.*',
                    'return Step::branch\\(\\$id, \\$this->condition.*',
                    'return new self\\(\\(int\\) \\$matches\\[1\\], \\$nested\\);',
                    '.*FIRST_RETRY_SECONDS << min\\(\\$run->attempt, 10\\).*',
                    '.*array_fill\\(0, \\\\count\\(\\$columns\\), .\\?.\\).*',
                    "'enabled' => 0,",
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*FIRST_RETRY_SECONDS << min\\(\\$run->attempt, 10\\).*',
                    '.*array_fill\\(0, \\\\count\\(\\$columns\\), .\\?.\\).*',
                    "'enabled' => 0,",
                ],
            ],
            // The steps of a branch are read from the depth after the branch: the `then` list is read first and
            // stops a definition too deep before the `else` list could.
            'Plus' => [
                'ignoreSourceCodeByRegex' => [
                    'array_push\\(\\$all, \\.\\.\\.self::collect\\(\\$step->then.*',
                    'return Step::branch\\(\\$id, \\$this->condition.*',
                ],
            ],
            // `isset()` is true for a key whose value is `false`: the value only serves the type of the list.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$ids\\[\\$step->id\\] = true;',
                    '\\$known\\[\\$spec->name\\] = true;',
                ],
            ],
            // The regular expression of a step path allows at most 164 characters: a test of 255 bytes ahead of it
            // refuses nothing more.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\\\strlen\\(\\$path\\) > 255 \\|\\|.*',
                ],
            ],
            // A condition of a filter or a branch is never null: the step builders require one.
            'NullSafePropertyCall' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\'language\' => \\$step->condition\\?->language.*',
                ],
            ],
            // The id and the type of a step are read, and refused when missing, before the keys of the step are
            // checked: their entry in the list of required keys is never the one that refuses.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$this->keys\\(\\$step, \\[.*\\], \\[.id., .type.*\\], \\$where\\);',
                    // A value bound as a string takes the default type of the connection, a string.
                    '.*\\[ParameterType::STRING\\].*',
                ],
            ],
            // An empty list of options is the options of none: reading it again gives the same.
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'return Options::none\\(\\);',
                ],
            ],
            // `executeStatement()` gives an integer, or a numeric string on a server that does so: the cast serves
            // the type of the comparison and shows only there, which the integration tests of the servers read.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    'return 1 === \\(int\\) \\$this->connection->executeStatement\\(',
                ],
            ],
            // Only an action step reaches this line, and its action is set: the cast serves the type of the call.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$type = \\(string\\) \\$step->action;',
                ],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['ExpressionLanguage', 'DBAL']],
];
