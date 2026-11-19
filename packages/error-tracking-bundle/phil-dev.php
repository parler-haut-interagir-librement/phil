<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths, its scripts, its fuzz target and its exceptions to the shared
 * configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // symfony/rate-limiter is installed wherever the tests run: the refusal is tested on the guard itself.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*RateLimitGuard::requireComponent\\(interface_exists.*'],
            ],
            // SQLite, on which the tests run, gives counts as integers and DBAL gives affected rows as integers: the
            // casts only matter on MySQL, which gives numeric texts, and a count is never anything but numeric.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return is_numeric\\(\$value\\) \? \(int\) \$value : 0;.*',
                    '.*return \(int\) \$events \+ \(int\) \$groups;.*',
                ],
            ],
            // `preg_replace()` without the `u` flag fails only past the backtracking limit, which a pattern of one class
            // never reaches: the cast of its result to a string is never anything but a string.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$message = \\(string\\) preg_replace\\(.*',
                    '.*\\$function = .* \\(string\\) preg_replace\\(.*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return is_numeric\\(\$value\\) \? \(int\) \$value : 0;.*',
                ],
            ],
            // The cut of the events of a group reads one id, past the cap: `fetchOne()` reads the first row whatever the
            // limit, so a limit of two gives the same id.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*ORDER BY id DESC\', 1, \$this->eventsPerGroup\).*',
                    '.*return is_numeric\\(\$value\\) \? \(int\) \$value : 0;.*',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // The parser of the browsers' reports reads what any browser, or anyone, posts to the endpoint.
        'targets' => ['browser' => 'tests/Fuzz/browser.php'],
        'budget' => 60,
        'corpus' => 'tests/Fuzz/corpus',
    ],
];
