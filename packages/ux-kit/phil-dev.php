<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // src/Test, the browser test case and the page of recipes published for the browser suites of other kits, is
        // test code: it sees PHPUnit, the WebDriver Panther drives and Twig, all development requirements, as the
        // infrastructure of a bundle sees what it integrates.
        'ruleset' => [
            'PhilUxKitTest' => ['DefaultAllowed', 'Forbidden'],
        ],
    ],
    'docs' => ['public' => true, 'words' => ['Sparkline', 'chromedriver', 'importmap', 'WCAG', 'seedable']],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API dépréciée de Symfony à remplacer.
            [
                'identifier' => 'return.deprecatedInterface',
                'path' => 'tests/Functional/App/TestKernel.php',
                'count' => 1,
            ],
        ],
    ],
];
