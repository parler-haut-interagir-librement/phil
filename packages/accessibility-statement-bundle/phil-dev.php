<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The numbers DBAL reads from the key column are integers, or numeric strings that an array key turns into
            // the same integers: the cast only serves the array<int, …> of the signature.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['\\$publications\\[\\(int\\) \\$version\\] = \\$moment;'],
            ],
            // Both packages are installed where the tests run: the check refuses when either is missing, and no test
            // can uninstall one of them.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['if \\(!interface_exists\\(AuthorizationCheckerInterface::class\\).*'],
            ],
        ],
    ],
    'docs' => [
        'public' => true,
        // The Web Content Accessibility Guidelines, by the name the W3C gives them.
        'words' => ['WCAG'],
    ],
];
