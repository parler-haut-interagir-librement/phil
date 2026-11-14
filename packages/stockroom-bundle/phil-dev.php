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
            // `(int) '1763046000' * 1000000` is an integer as well: PHP multiplies the digits of the text as a number,
            // and adds the text of the microseconds as one.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['return \\(int\\) \\$moment->format\\(.U.\\) \\* self::MICROSECONDS \\+ \\(int\\) \\$moment->format\\(.u.\\);'],
            ],
        ],
    ],
];
