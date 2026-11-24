<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The HTML5 parser always builds an html element, even for an empty string: `documentElement` is null only
            // in PHPStan's view of `Dom\Document`, which the null-safe call answers.
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => ['.*\\$language = \\$document->documentElement\\?->getAttribute\\(.lang.\\);.*'],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'deptrac' => [
        // The rules read HTML through the DOM extension and addresses through PHP's URI extension and phil/url; the
        // classes of Bridge\ each see the library they adapt, phil/structured-data, phil/social-cards or
        // phil/link-checker.
        'layers' => [
            'PhilSeoCheck' => '^Phil\\\\SeoCheck\\\\(?!Bridge\\\\).*',
            'PhilSeoCheckBridge' => '^Phil\\\\SeoCheck\\\\Bridge\\\\.*',
        ],
        'ruleset' => [
            'PhilSeoCheck' => ['DefaultAllowed', 'PhilPublishing', 'PhilUrl', 'Forbidden'],
            'PhilSeoCheckBridge' => ['PhilSeoCheck', 'PhilStructuredData', 'PhilSocialCards', 'PhilLinkChecker', 'PhilUrl', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // The markup reader and the default rules on pages nobody wrote, from the seeds of
        // tests/Fuzz/corpus/seo-check-page.
        'targets' => [
            'seo-check-page' => 'tests/Fuzz/page.php',
        ],
    ],
];
