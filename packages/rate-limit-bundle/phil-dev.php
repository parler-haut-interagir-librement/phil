<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        'ignoreErrors' => [
            // The polyfills of symfony/string, which the shims of this fixture call, are installed with the bundle on
            // its own; a monorepo that requires the intl and mbstring extensions replaces them, and its test skips.
            [
                'identifier' => 'class.notFound',
                'path' => 'tests/Unit/Fixture/polyfilled-string.php',
            ],
            [
                'identifier' => 'return.type',
                'path' => 'tests/Unit/Fixture/polyfilled-string.php',
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true, 'words' => ['HMAC']],
    'infection' => [
        'mutators' => [
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    // The name of a form as the key of the rules: PHP turns a numeric string key into an integer either
                    // way, so the cast changes nothing; it only serves PHPStan's array<string, …>.
                    '\$rules\[\(string\) \$form\]\[\] = .*',
                    // The seconds in the parameters of a message: strtr() and the translator read an integer as its
                    // digits, so the cast changes nothing; it only serves the array<string, string> of the contracts.
                    '\$detail = \$this->translated\(self::DETAIL, \[.%seconds%. => \(string\) \$seconds\]\);',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // The default of the wait of a refusal of the framework without a Retry-After: -1 is clamped to 0 by
                    // the max() that follows, as 0 is, so no observable output differs.
                    '\$retryAfter = \$exception->getHeaders\(\)\[.Retry-After.\] \?\? 0;',
                    // The default of the tokens of reserve(), which refuses every call whatever its arguments: the
                    // signature only repeats the one of LimiterInterface.
                    'public function reserve\(int \$tokens = 1, \?float \$maxTime = null\): Reservation',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // The default of the tokens of reserve(), which refuses every call whatever its arguments: the
                    // signature only repeats the one of LimiterInterface.
                    'public function reserve\(int \$tokens = 1, \?float \$maxTime = null\): Reservation',
                ],
            ],
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => [
                    // A moment's "U.u" text is a numeric string, which PHP subtracts as the same float; the cast only
                    // serves the strict arithmetic of PHPStan.
                    'return max\(0, \(int\) ceil\(\(float\) \$moment->format\(.U\.u.\) - \$now\)\);',
                ],
            ],
        ],
    ],
];
