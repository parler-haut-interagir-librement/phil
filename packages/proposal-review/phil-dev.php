<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'infection' => [
        'mutators' => [
            // A conflict has a proposal or an author, never both: either order of `??` reads the one there is.
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '.*Tables::identifier\\(\\$conflict->proposal \\?\\? .*',
                    '.*\\(null === \\$conflict->proposal \\? \'b\' : \'a\'\\)\\.\\(\\$conflict->proposal \\?\\? .*',
                ],
            ],
            // Every field the format reads is in the stored text, so that nothing is left for `!` to reset.
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*createFromFormat\\(\'!\'\\.self::INSTANT.*'],
            ],
            // Maps that only ever answer isset(): the value kept under a key is never read.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => ['\\$judged\\[.*\\] = true;'],
            ],
            // Every eligible proposal starts from the same exposure: only the differences between them order the draw.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['\\$exposure = array_fill_keys\\(.*'],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$exposure = array_fill_keys\\(.*',
                    // The bisection on the scale runs a fixed 60 steps at most and stops on an interval below 1e-15:
                    // a step more or less, an interval of exactly 1e-15 and a balance of exactly zero move the root by
                    // less than the last digit.
                    'for \\(\\$step = 0; \\$step < 60 && \\$high - \\$low > 1e-15; \\+\\+\\$step\\) \\{',
                ],
            ],
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    'for \\(\\$step = 0; \\$step < 60 && \\$high - \\$low > 1e-15; \\+\\+\\$step\\) \\{',
                    // `<` and `<=` differ for two equal identifiers, which are the same pair either way; mirroring the
                    // two orders of a pair gives the same key for both of its directions.
                    'return strcmp\\(\\$first, \\$second\\) < 0 \\?.*',
                ],
            ],
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    'for \\(\\$step = 0; \\$step < 60 && \\$high - \\$low > 1e-15; \\+\\+\\$step\\) \\{',
                    'if \\(\\$balance > 0\\) \\{',
                ],
            ],
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['for \\(\\$step = 0; \\$step < 60 && \\$high - \\$low > 1e-15; \\+\\+\\$step\\) \\{'],
            ],
            'Ternary' => [
                'ignoreSourceCodeByRegex' => ['return strcmp\\(\\$first, \\$second\\) < 0 \\?.*'],
            ],
            // The list type of the signature: the callers only walk or sort what they get.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['return array_values\\(array_filter\\(.*'],
            ],
        ],
    ],
    'deptrac' => [
        // The standings and the pairwise scores sort by `\SortDirection`, from PHP 8.6 or symfony/polyfill-php86.
        'allow' => ['^SortDirection$'],
        // The core is plain PHP; the DBAL stores are an adapter, which alone sees Doctrine.
        'layers' => [
            'PhilProposalReview' => '^Phil\\\\ProposalReview\\\\(?!Dbal\\\\).*',
            'PhilProposalReviewAdapter' => '^Phil\\\\ProposalReview\\\\Dbal\\\\.*',
        ],
        'ruleset' => [
            'PhilProposalReviewAdapter' => ['PhilProposalReview', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
];
