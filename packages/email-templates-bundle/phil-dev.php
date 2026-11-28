<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The store of the contents and its entity read and write through Doctrine DBAL.
        'allow' => ['^Doctrine\\\\DBAL\\\\.*'],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // The parser of HTML5 always gives a document a body, so the fallback to the document is never taken.
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => ['.*\\$converter->walk\\(\\$document->body \\?\\? \\$document\\);.*'],
            ],
            // The name of an element of an HTML document is already in lowercase: the parser writes it so.
            'UnwrapStrToLower' => [
                'ignoreSourceCodeByRegex' => ['.*strtolower\\(\\$(element|parent|sibling|cell)->localName\\).*'],
            ],
            // The text is tidied at the end — a line break more or a blank less at the start or twice in a row, and a
            // space before a break, are all taken out —, so the checks that spare the work do not show in the text.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    '.*if \\(\'\' !== \\$this->out && !str_ends_with\\(\\$this->out, "\\\\n\\\\n"\\)\\) \\{.*',
                    // What the definition checks first: once the reasons are empty, the format and the schema are there,
                    // and the assertion only tells PHPStan so.
                    '.*\\\\assert\\(\\\\is_string\\(\\$subject\\) && .*',
                ],
            ],
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['.*\\[\\] !== \\$errors \\|\\| !\\$format instanceof BodyFormat \\|\\| !\\$schema instanceof VariableSchema.*'],
            ],
            'LogicalOrAllSubExprNegation' => [
                'ignoreSourceCodeByRegex' => ['.*\\\\assert\\(\\\\is_string\\(\\$subject\\) && .*'],
            ],
            // The reasons are listed alone only when the format and the schema are there, which they are once none is.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*throw InvalidTemplate::withErrors\\(\\[\\] === \\$errors \\? \\[\'The template is not valid.\'\\] : \\$errors\\);.*',
                    // Twig writes nothing of the escaping to an output the filter already marks as safe.
                    '.*new Environment\\(new ArrayLoader\\(\\[\'inline\' => .*\\[\'autoescape\' => false, \'cache\' => false\]\\);.*',
                ],
            ],
            // The directory is given a root: without one, the first test already refuses the image.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$file = false === \\$root \\? false : realpath\\(.*'],
            ],
            // A line break and the spaces before it: the text is tidied at the end, line by line.
            'UnwrapRtrim' => [
                'ignoreSourceCodeByRegex' => ['\\$this->out = rtrim\\(\\$this->out, \' \'\\);'],
            ],
            // The reasons are those of distinct variables, fields and parts: none comes twice, and a list stays a list.
            'UnwrapArrayUnique' => [
                'ignoreSourceCodeByRegex' => ['throw InvalidTemplate::withErrors\\(array_values\\(array_unique\\(\\$errors\\)\\)\\);'],
            ],
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    'throw InvalidTemplate::withErrors\\(array_values\\(array_unique\\(\\$errors\\)\\)\\);',
                    // The keys of the option of a console command given as an array are already 0, 1, 2...
                    '.*array_values\\(array_filter\\(\\(array\\) \\$input->getOption\\(\'segment\'\\), is_string\\(\\.\\.\\.\\)\\)\\).*',
                    // The names of the enabled bundles are only searched, never indexed.
                    '.*array_values\\(array_filter\\(array_keys\\(\\$bundles\\), is_string\\(\\.\\.\\.\\)\\)\\).*',
                ],
            ],
            'UnwrapArrayFilter' => [
                'ignoreSourceCodeByRegex' => [
                    // The values of a console option given as an array are strings: the filter only tells PHPStan so.
                    '.*array_filter\\(\\(array\\) \\$input->getOption\\(\'segment\'\\), is_string\\(\\.\\.\\.\\)\\).*',
                    // The names of the bundles are strings, as the kernel writes them: the filter only tells PHPStan so.
                    '.*array_filter\\(array_keys\\(\\$bundles\\), is_string\\(\\.\\.\\.\\)\\).*',
                ],
            ],
            // The origin of the template checked is not read by anything the check does with the render.
            'Ternary' => [
                'ignoreSourceCodeByRegex' => ['.*\\$site->isShared\\(\\) \\? TemplateOrigin::Shared : TemplateOrigin::Site\\);.*'],
            ],
            // 262144 / 1024 and 262144 / 1023 are 256 and 256.25, which a %d writes alike.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*self::MAX_BODY_BYTES / 1024\\);.*',
                    // A stored version or a stored date that is not a number is read as zero, and never below one.
                    '.*\\? \\(int\\) \\$value : 0\\);.*',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // A limit of two rows rather than one: the first row is the one read.
                    '.*modifyLimitQuery\\(.*, 1\\).*',
                    '.*\\? \\(int\\) \\$value : 0\\);.*',
                ],
            ],
            // Twig is told what to keep of a template: the values only mark a name as seen, and are never read.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['.*\\$this->(set|read|calls)\\[.*\\] = true;.*'],
            ],
            // A sandbox built by the line before, a draft or a version the store has just written and reads back: the
            // throw tells PHPStan so, and cannot be reached.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\?\\? throw RenderingFailed::because\\(\'The sandbox is not built.\'\\);.*',
                    '.*\\?\\? throw UnknownTemplate::(draft|version)\\(\\$key->value, \\$locale->value.*\\);.*',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // A template source and the values given to it, nobody's: what is not an exception of the bundle is a crash.
        'targets' => [
            'render' => 'tests/Fuzz/render.php',
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside the fuzzer, which gives it what PHPStan does not know.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
