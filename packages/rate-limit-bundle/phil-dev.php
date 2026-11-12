<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
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
