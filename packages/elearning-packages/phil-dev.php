<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // `getExternalAttributesIndex()` sets both before they are read; when it fails, the system is not Unix
            // whatever its starting value, and no link is seen.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['\\$system = 0;', '\\$attributes = 0;']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['\\$system = 0;', '\\$attributes = 0;']],
            // `fread()` that fails adds `false`, which is the empty string.
            'CastString' => ['ignoreSourceCodeByRegex' => ['.*\\(string\\) fread\\(.*']],
            // `masteryScore()` has checked the value with `is_numeric()`: PHP compares and divides a numeric string
            // as the float it casts to, and the return type makes the float of an integer quotient.
            'CastFloat' => ['ignoreSourceCodeByRegex' => ['\\$score = \\(float\\) \\$value;']],
            // An element of a document always has its document: `?->` and `->` read the same.
            'NullSafeMethodCall' => ['ignoreSourceCodeByRegex' => ['.*ownerDocument\\?->saveXML.*']],
        ],
    ],
    'fuzz' => [
        // The readers on bytes nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'scorm-manifest' => 'tests/Fuzz/scorm-manifest.php',
            'cmi5-structure' => 'tests/Fuzz/cmi5-structure.php',
            'zip-package' => 'tests/Fuzz/zip-package.php',
        ],
    ],
    'docs' => [
        'public' => true,
        // The terms of SCORM, IMS Content Packaging and cmi5.
        'words' => ['cmi5', 'SCORM', 'SCO', 'SCOs', 'xAPI', 'imsmanifest', 'moveOn', 'masteryScore', 'launchMethod', 'langstring', 'mebibyte', 'XXE', 'elearning', 'unserializes'],
    ],
];
