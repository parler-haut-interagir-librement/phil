<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    // Incubating: the French mirror follows the English when it can, and is not a gate yet.
    'docs' => ['public' => true, 'translations' => 'advisory'],
];
