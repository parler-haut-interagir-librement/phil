<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The code of an exception chained to its cause is the default zero, which nothing reads; and the language
            // alone is the first piece of the tag, whether the tag is cut in two pieces or in three.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*, 0, \\$\\w+\\);', '.*explode\\(.*, 2\\)\\[0\\].*']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*, 0, \\$\\w+\\);']],
            // A confidence the service gave as an integer, 0 or 1, is a float once the segment takes it: `Segment` types it so.
            'CastFloat' => ['ignoreSourceCodeByRegex' => ['.*\\$confidence = \\\\is_int\\(\\$confidence\\) \\|\\| .*']],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['Deepgram', 'diarize', 'keyterm', 'WCAG']],
    'fuzz' => [
        // Each reader on responses nobody wrote, from the seeds of tests/Fuzz/corpus/<target>: whatever it reads, it
        // returns a transcript or throws an exception of the library.
        'targets' => [
            'verbose-json' => 'tests/Fuzz/verbose-json.php',
            'deepgram' => 'tests/Fuzz/deepgram.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
