<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'fuzz' => [
        // An item made of text nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'feed-item' => 'tests/Fuzz/feed-item.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // The directory is checked writable just before: `tempnam()` falls back to the system's directory only when it
            // is not, and a write is short only on a full disk. Neither can be made to happen in a test, and with `&&`
            // the conditions that follow are the ones the refusals already cover.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(false === \\$temporary \\|\\| \\\\dirname\\(\\$temporary\\) !== realpath\\(\\$directory\\)\\) \\{',
                    'if \\(false === \\$written \\|\\| \\\\strlen\\(\\$text\\) !== \\$written\\) \\{',
                ],
            ],
            // The digits are checked by the pattern before they are added up: each is a digit, which PHP multiplies as a
            // number with or without the cast.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['\\$sum \\+= \\(int\\) \\$digits\\[\\$i\\] \\* \\$weight;'],
            ],
            // The text was checked to be UTF-8 just before, the one way `preg_replace()` fails on a pattern that holds
            // the `u` modifier: it never gives `null` here.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['\\$text = \\(string\\) preg_replace\\(\'/\\[\\\\x00-.*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
