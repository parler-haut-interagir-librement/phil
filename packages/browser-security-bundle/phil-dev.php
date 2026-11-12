<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths, its fuzz target and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['deduplicator', 'HMAC', 'HSTS']],
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
            // `preg_replace()` gives `null` only on an error its fixed patterns cannot meet, and `stream_get_contents()`
            // gives `false` only on a stream already closed: the cast changes nothing that can be observed. A key cast
            // to a string is turned back into an integer by PHP when it is one: the cast keeps the type checker sure
            // of what the keys are.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\(string\\) preg_replace\\(.*',
                    '.*\\(string\\) stream_get_contents\\(.*',
                    '.*\\$directives\\[\\(string\\) \\$directive\\] = .*',
                    '.*\\$policies\\[\\(string\\) \\$name\\] = .*',
                    '.*\\$features\\[\\(string\\) \\$feature\\] = .*',
                    '.*\\$paths\\[\\(string\\) \\$path\\] = .*',
                ],
            ],
            // The sources of a directive of user content go to Policy, which reads them as values into a list of its own:
            // the keys a configuration gives never reach a header.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*\\\\is_array\\(\\$values\\) => array_values\\(\\$values\\),.*'],
            ],
            // In a value of a protection, a key is a string exactly when its item is a list: either test alone picks the
            // groups of a map, and `||` changes nothing that can be observed.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    '.*if \\(\\\\is_string\\(\\$key\\) && \\\\is_array\\(\\$item\\)\\) \\{.*',
                ],
            ],
            // The redirect is on when the value is not null: `false` in its place gives the same verdict.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*protection\\(Protection::HttpsRedirect, \\$this->enabled \\? true : null\\).*',
                ],
            ],
            // The endpoint reads one byte more than the bound to tell a body too large; two more give the same verdict.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*stream_get_contents\\(\\$request->getContent\\(true\\), \\$this->receiver->maxBytes\\(\\) \\+ 1\\).*',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // The report parser reads what any browser, or anyone, posts to the endpoint.
        'targets' => ['report' => 'tests/Fuzz/report.php'],
        'budget' => 60,
        'corpus' => 'tests/Fuzz/corpus',
    ],
];
