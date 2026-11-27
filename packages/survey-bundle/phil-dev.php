<?php

declare(strict_types=1);

use Phil\SurveyBundle\Application\Import\QuestionImportTarget;

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'js' => ['paths' => ['assets', 'tests/js']],
    'twig' => ['paths' => ['templates']],
    'fuzz' => [
        // Text nobody wrote, from the seeds of tests/Fuzz/corpus/<target>: questionnaire documents read by the JSON
        // exchange format.
        'targets' => [
            'survey-questionnaire-json' => 'tests/Fuzz/survey-questionnaire-json.php',
            // The CSV files of questions people upload, read and planned by the profile of the questions.
            'survey-question-csv' => 'tests/Fuzz/survey-question-csv.php',
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // A mark only has to exist: the condition reads it with isset(), which is true for false as for true.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$read\\[\\$this->origin\\(\\$url\\)\\] = true;.*',
                    '.*\\$readable\\[\\$question->name\\] = true;.*',
                ],
            ],
            // A matrix has at least one row, so the lowest count of its rows is always set: the fallback never runs.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$least \\?\\?= 0;.*',
                    // A refused ballot of valid, distinct choices holds more than one, and never as many as the lowest.
                    '.*return \\\\count\\(\\$choices\\) < \\(\\$this->question->min \\?\\? 1\\).*',
                    // The bounds of the random wait before a retry: nothing tells 1 ms more or less from the others.
                    '.*usleep\\(random_int\\(1_000, 10_000\\) \\* \\$attempt\\);.*',
                    // The placeholders are written from their number, not from the keys of the array that lists them.
                    '.*array_fill\\(0, \\\\count\\(\\$row\\), \'\\?\'\\).*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$least \\?\\?= 0;.*',
                    '.*return \\\\count\\(\\$choices\\) < \\(\\$this->question->min \\?\\? 1\\).*',
                    '.*usleep\\(random_int\\(1_000, 10_000\\) \\* \\$attempt\\);.*',
                    '.*array_fill\\(0, \\\\count\\(\\$row\\), \'\\?\'\\).*',
                ],
            ],
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['.*return \\\\count\\(\\$choices\\) < \\(\\$this->question->min \\?\\? 1\\).*'],
            ],
            // Only a ballot of a multiple choice or a ranking reaches the check, and their rules are never null.
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->question->rules\\(\\)\\?->ballot.*'],
            ],
            // An array key made of digits is a whole number whichever way it is written.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*isset\\(\\$ranks\\[\\(int\\) \\$rank\\]\\).*',
                    '.*\\$ranks\\[\\(int\\) \\$rank\\] = .*',
                ],
            ],
            // The type of a variable that is no choice has no choices to list: the list is empty either way.
            'LogicalOrAllSubExprNegation' => [
                'ignoreSourceCodeByRegex' => ['.*\\$chooses = VariableType::Choice === \\$type.*'],
            ],
            // The format of an instant holds every field the "!" would reset.
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*createFromFormat\\(\'!\'\\.DateTimeInterface::ATOM, \\$text\\).*',
                    // A style the formatter leaves open ends with the line, which is the last the command writes.
                    '.*writeln\\(\'<error>\'\\.\\$[a-zA-Z]+->getMessage\\(\\)\\.\'</error>\'\\);.*',
                    '.*writeln\\(\'<error>\'\\.OutputFormatter::escape\\(\\$[a-zA-Z]+->getMessage\\(\\)\\)\\.\'</error>\'\\);.*',
                ],
            ],
            // The sections left once one is removed are read at once by the search for another, or by the spread that
            // adds a section, and both start the numbering again.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*return array_values\\(array_filter\\(\\$replaced, .*'],
            ],
            // A choice of the cell is trimmed before the pattern reads it: what follows its "=" never holds only blanks.
            'UnwrapTrim' => [
                'ignoreSourceCodeByRegex' => ['.*\'\' !== trim\\(\$parts\[2\]\).*'],
            ],
            // The early return of a batch no row of which could be read only spares building the questionnaire again: it
            // comes out the same, or is refused for having no section, and nothing is saved either way.
            'ReturnRemoval' => [
                'ignore' => [QuestionImportTarget::class.'::write'],
            ],
            // A questionnaire the check misses is inserted, the insert is refused by its key, and the update follows: the same row.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$types = \\[\'definition\' => Types::TEXT.*',
                    '.*WHERE questionnaire_key = \\?\', \\[\\$key\\]\\);.*',
                    // The types of the columns the drivers bind as text, or as a whole number, without being told.
                    '.*\\[\'position\' => Types::SMALLINT, \'body\' => Types::TEXT.*',
                    '.*\\], \\[\'answers\' => Types::TEXT, \'computed\' => Types::TEXT.*',
                ],
            ],
        ],
    ],
];
