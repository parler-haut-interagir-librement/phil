<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'words' => ['HMAC']],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // The values of a console argument or option given as an array are strings: the filter only tells PHPStan
            // so, and nothing else reaches the commands.
            'UnwrapArrayFilter' => [
                'ignoreSourceCodeByRegex' => [
                    '.*array_filter\\(\\(array\\) \\$input->get(Option|Argument)\\(\'(scope|id)\'\\), \\\\is_string\\(\\.\\.\\.\\)\\).*',
                ],
            ],
            // Whether the form component, Twig and the CSRF component are installed: the development environment has
            // all three, so only the flag the tests pass decides; the look itself cannot be told apart from its parts.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    '.*class_exists\\(AbstractType::class\\) && class_exists\\(Environment::class\\) && interface_exists.*',
                    // The same for the endpoint of GitHub's secret scanning and its dependencies.
                    '.*interface_exists\\(HttpClientInterface::class\\) && interface_exists\\(CacheInterface::class\\) && class_exists.*',
                ],
            ],
            // A DQL DELETE returns the number of rows removed, an integer on every driver: the fallback only tells
            // PHPStan so.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['return \\\\is_int\\(\\$removed\\) \\? \\$removed : 0;'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['return \\\\is_int\\(\\$removed\\) \\? \\$removed : 0;'],
            ],
        ],
    ],
    'fuzz' => [
        // The reader of tokens and the two readers of GitHub's secret scanning, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'token' => 'tests/Fuzz/token.php',
            'github-leak-report' => 'tests/Fuzz/github-leak-report.php',
            'github-public-keys' => 'tests/Fuzz/github-public-keys.php',
        ],
    ],
];
