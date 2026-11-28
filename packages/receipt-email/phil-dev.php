<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['preheader']],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // array_map() over several arrays walks their values in order and numbers its result anew.
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => ['.*\'tax_numbers\' => array_map\\(.*']],
            // Symfony's Address, a final class, trims the name it is given already.
            'UnwrapTrim' => ['ignoreSourceCodeByRegex' => ['.*\\$name = trim\\(\\$this->address->getName\\(\\)\\);.*']],
            // A CSS without url() gives no match to walk, whether 0 counts or not.
            'GreaterThan' => ['ignoreSourceCodeByRegex' => ['.*preg_match_all\\(.*\\$css, \\$matches\\) > 0.*']],
            // Only the first piece of these explode() calls is read: cut in two or in three, it is the same. The rule
            // takes the [0] of the same lines with it, whose [1] a string without the separator would lack.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*explode\\(\'[#=_]\', .*, 2\\)\\[0\\].*']],
            // The value is valid UTF-8 already, where a byte under 0x20 or 0x7F is always that character.
            'PregMatchRemoveFlags' => ['ignoreSourceCodeByRegex' => ['.*preg_match\\(\'/\\[\\\\x00-\\\\x1F\\\\x7F\\]/u\', \\$value\\).*']],
        ],
    ],
    'fuzz' => [
        // The transactional checks of a rendered receipt, from the seeds of tests/Fuzz/corpus/transactional-html.
        'targets' => [
            'transactional-html' => 'tests/Fuzz/transactional.php',
        ],
    ],
    'deptrac' => [
        // The core — the domain and the application — is a library: Symfony, phil/money and the classes of PHP's DOM
        // extension, `Dom\HTMLDocument` and `Dom\Element`, which TransactionalCheck reads the HTML with and which
        // Deptrac counts in `Forbidden` (an `allow` of them would take them from every other package that reaches them
        // that way); composer-dependency-analyser still refuses any package the core does not require. The adapters of
        // Infrastructure see the packages they adapt, each optional: the receipts of phil/commerce-bundle and its PDF,
        // the renderer of phil/email-templates-bundle, phil/paged-media; never the other way round.
        'layers' => [
            'PhilReceiptEmail' => ['^Phil\\\\ReceiptEmail\\\\(?:Domain|Application)\\\\.*'],
            'PhilReceiptEmailInfrastructure' => ['^Phil\\\\ReceiptEmail\\\\Infrastructure\\\\.*'],
        ],
        'ruleset' => [
            'PhilReceiptEmail' => ['DefaultAllowed', 'PhilMoney', 'Forbidden'],
            'PhilReceiptEmailInfrastructure' => [
                'PhilReceiptEmail',
                'DefaultAllowed',
                'PhilMoney',
                'PhilCommerceBundleDomain',
                'PhilCommerceBundleInfrastructure',
                'PhilEmailTemplatesBundleDomain',
                'PhilEmailTemplatesBundleApplication',
                'PhilPagedMedia',
            ],
        ],
    ],
];
