<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The stand-in for a missing slowness threshold only passes the check of at least 1: any positive value
            // passes it the same.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\'slowness threshold\' => \\$slowCallMilliseconds \\?\\? 1,',
                ],
            ],
            // No slow call given: -1 slow calls never reach a rate of at least 1 % of at least one call, and a restored
            // snapshot keeps at least 0 slow calls.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'public function trips\\(int \\$calls, int \\$failures, int \\$slowCalls = 0\\): bool',
                    'public static function restore\\(.*int \\$slowCalls = 0\\): self',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
