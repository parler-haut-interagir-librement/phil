<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The code of an exception chained to its cause is the default zero, which nothing reads; and a progress body
            // nested more deeply than its two levels is answered by 400 whether the decoder refuses it or the check that its
            // fields are strings does.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => ['.*, 0, \\$\\w+\\);', '.*json_decode\\(\\$body, true, 2, .*']],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => ['.*, 0, \\$\\w+\\);']],
            // array_pad() numbers the keys of its result from zero: the counts are a list before array_values() as after.
            'UnwrapArrayValues' => ['ignoreSourceCodeByRegex' => ['.*new Heatmap\\(\\$heatmap->viewings \\+ 1, array_values\\(\\$counts\\)\\);']],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['HLS', 'Vimeo', 'WCAG']],
    'fuzz' => [
        // The inputs the bundle reads from outside: the address an author pastes, and the body a browser posts. Whatever
        // they hold, a video is built or InvalidVideo thrown, and the controller answers with a status.
        'targets' => [
            'linked-url' => 'tests/Fuzz/linked-url.php',
            'progress' => 'tests/Fuzz/progress.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
