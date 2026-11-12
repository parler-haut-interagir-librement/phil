<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['APCu', 'Memcached']],
];
