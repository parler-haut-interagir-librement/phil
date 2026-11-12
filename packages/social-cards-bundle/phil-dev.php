<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The fetcher reads the answer chunk by chunk and cancels it: a buffered answer would also keep what was
            // read, in memory, and give the same page; asking for the headers with `true` throws on a status other than
            // 200, which is refused anyway.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    "'buffer' => false,",
                    '\$type = \$response->getHeaders\(false\).*',
                ],
            ],
            // A cancelled answer streams nothing but the exception of its cancellation, which gives no page as well: the
            // `return` and the `break` after a cancel only spare reading it.
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => ['return null; // cancelled'],
            ],
            'Break_' => [
                'ignoreSourceCodeByRegex' => ['break; // cancelled'],
            ],
            // A chunk that reaches the bound exactly is followed by one more read, then cut at the bound: the page is
            // the same.
            'GreaterThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['if \(\\\\strlen\(\$html\) >= \$this->maxBytes \|\| .*'],
            ],
            // The depth of a request's JSON is a bound against a hostile file: a file one level deeper or shallower than
            // the bound is refused alike, for its shape.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\$values = json_decode\(\$json, true, 4, .*',
                    // The bounds of the random part of a temporary name: any other number names another file, renamed
                    // over the request all the same.
                    '.*\\$temporary = \\\\sprintf\\(\'%s\\.%08x\\.tmp\', \\$path, random_int\\(0, 0xFFFFFFFF\\)\\);.*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\$values = json_decode\(\$json, true, 4, .*',
                    // The bounds of the random part of a temporary name: any other number names another file, renamed
                    // over the request all the same.
                    '.*\\$temporary = \\\\sprintf\\(\'%s\\.%08x\\.tmp\', \\$path, random_int\\(0, 0xFFFFFFFF\\)\\);.*',
                ],
            ],
        ],
    ],
    'docs' => ['public' => true],
];
