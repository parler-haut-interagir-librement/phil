<?php

declare(strict_types=1);

use Phil\AccessBundle\Infrastructure\Security\AccessVoter;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The tuples read for one visibility marker, one owner or one space: the first of one or two is the same
            // tuple, as every read is ordered; and the owner's bits, which only ever meet the default arm of the
            // match whether they are 0 or 1.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*AccessModel::VISIBILITY, 1\\)\\[0\\].*',
                    '.*.owner., 1\\)\\[0\\].*',
                    '.*.space., 1\\)\\[0\\].*',
                    '.*self::Owner => 0,.*',
                ],
            ],
            // The model takes its types as a variadic list: keys, named or not, change nothing.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*return new Model\\(\\.\\.\\.array_values\\(\\$types\\)\\);.*'],
            ],
            // `array_search` gives an int for a name that is there, which `assert()` has just made sure of; the cast
            // tells PHPStan.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\(int\\) \\$index - 1.*'],
            ],
            // An `assert()` is a statement for static analysis, removed in production.
            'LogicalOrAllSubExprNegation' => [
                'ignore' => [AccessVoter::class.'::voteOnAttribute'],
            ],
            // The sentence that describes an option in `config:dump-reference`.
            'Concat' => [
                'ignoreSourceCodeByRegex' => ['.*->info\\(.*'],
            ],
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*->info\\(.*'],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['EasyAdmin']],
];
