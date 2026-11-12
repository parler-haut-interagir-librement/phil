<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // Two layers rather than those of a bundle: the bundle and its Twig functions, which read phil/url, and
        // phil/structured-data when it is installed, for the ImageObject of an image; and
        // src/Test, the browser test case and the page of recipes published for the browser suites of other kits, which
        // is test code: it sees PHPUnit, the WebDriver Panther drives and Twig, all development requirements, as the
        // infrastructure of a bundle sees what it integrates.
        'layers' => [
            'PhilUxKit' => '^Phil\\\\UxKit\\\\(?!Test\\\\).*',
            'PhilUxKitTest' => '^Phil\\\\UxKit\\\\Test\\\\.*',
        ],
        'ruleset' => [
            'PhilUxKit' => ['DefaultAllowed', 'Forbidden', 'PhilUrl', 'PhilStructuredData'],
            'PhilUxKitTest' => ['DefaultAllowed', 'Forbidden'],
        ],
    ],
    'docs' => ['public' => true, 'words' => ['Sparkline', 'chromedriver', 'importmap', 'WCAG', 'seedable', 'IPTC', 'lightbox', 'lightboxes', 'CSSOM', 'LiipImagine']],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API dépréciée de Symfony à remplacer.
            [
                'identifier' => 'return.deprecatedInterface',
                'path' => 'tests/Functional/App/TestKernel.php',
                'count' => 1,
            ],
            [
                'identifier' => 'return.deprecatedInterface',
                'path' => 'tests/Functional/App/LiipImagineKernel.php',
                'count' => 1,
            ],
        ],
    ],
];
