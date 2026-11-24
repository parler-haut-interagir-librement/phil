<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'fuzz' => [
        // Entries nobody wrote: whatever they hold, each writer gives a document its parser reads, or the bundle
        // refuses the entry.
        'targets' => [
            'write' => 'tests/Fuzz/write.php',
        ],
    ],
    'phpstan' => [
        // The fuzz target runs inside the fuzzer, which gives it what PHPStan does not know.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
