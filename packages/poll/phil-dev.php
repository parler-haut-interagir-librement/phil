<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
    'infection' => [
        'mutators' => [
            // The options are identified before their marks are read, and an identifier admits no line break: the "D" flag
            // of the whole-number pattern only guards what the identifiers already refuse, so its mutant is equivalent.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['.*preg_match.*\\$lowest.*'],
            ],
            // A mark is a numeric string checked by PollRules::of() against the whole numbers: PHP reads it as the same
            // number with or without the cast, so the mutants without the cast are equivalent.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$sum \\+= \\(int\\) \\$rules->options\\[\\$position\\] \\* \\$count;',
                    '\\$marks = array_map\\(strval\\(\\.\\.\\.\\), range\\(\\(int\\) \\$lowest, \\(int\\) \\$lowest \\+ \\\\count\\(\\$options\\) - 1\\)\\);',
                ],
            ],
        ],
    ],
];
