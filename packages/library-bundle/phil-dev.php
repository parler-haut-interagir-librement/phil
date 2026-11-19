<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // The string is trimmed first, so that no line feed is left at its end for the `D` of the shape to refuse.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['.*preg_match\\(\'/\\^\\(\\\\d\\{9\\}\\[\\\\dX\\]\\|\\\\d\\{13\\}\\)\\$/D\', \\$digits\\).*'],
            ],
            // The digit is a numeric string, which the product takes as the integer it reads.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\$sum \\+= \\(\'X\' === \\$digit \\? 10 : \\(int\\) \\$digit\\) \\* \\(10 - \\$position\\);'],
            ],
            // A sum of the opposite sign is a multiple of 11 when the sum is.
            'PlusEqual' => [
                'ignoreSourceCodeByRegex' => ['.*\\$sum \\+= \\(\'X\' === \\$digit \\? 10 : \\(int\\) \\$digit\\) \\* \\(10 - \\$position\\);'],
            ],
        ],
    ],
    // PHP's own sort direction, which the ORM takes; on PHP 8.5 it comes from symfony/polyfill-php86, in the global
    // namespace that the default layers do not cover.
    'deptrac' => ['allow' => ['^SortDirection$']],
];
