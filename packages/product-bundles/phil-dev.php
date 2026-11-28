<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'phpstan' => [
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        'targets' => [
            'product-bundles-stored' => 'tests/Fuzz/product-bundles-stored.php',
        ],
    ],
];
