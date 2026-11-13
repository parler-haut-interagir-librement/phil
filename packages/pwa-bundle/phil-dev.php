<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // A backed enumeration is written to JSON as its value, so mapping the modes to their values first gives the same document.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => ['.*\'display_override\' => array_map\\(static fn \\(DisplayMode \\$mode\\): string => \\$mode->value.*'],
            ],
            // The origin of a path being resolved: the address of an origin alone and the one ending in a slash have the same path "/".
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*Url::parse\\(\\$value, Url::parse\\(self::ORIGIN\\.\'/\'\\)\\).*'],
            ],
            // The path of the request always starts with a slash, so strrpos() is never false and the cast changes nothing.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*substr\\(\\$path, 0, \\(int\\) strrpos\\(\\$path, \'/\'\\) \\+ 1\\).*'],
            ],
        ],
    ],
    'fuzz' => [
        // The manifest read from JSON nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'manifest' => 'tests/Fuzz/manifest.php',
        ],
    ],
    'docs' => [
        'public' => true,
        // A path cached when the worker installs, and the value of X-Content-Type-Options.
        'words' => ['precached', 'Precached', 'nosniff'],
    ],
    'phpstan' => [
        // The fuzz targets run inside the fuzzer, which gives them what PHPStan does not know.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
