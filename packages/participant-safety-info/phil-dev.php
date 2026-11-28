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
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // Once the limit is reached, `continue` deletes nothing more either: the next records see `$deleted >= $limit`
            // again, so the loop ends with the same result, only after walking the rest of the records.
            'Break_' => [
                'ignoreSourceCodeByRegex' => ['.*break;.*'],
            ],
        ],
    ],
    'fuzz' => [
        // Plaintexts nobody encoded, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'participant-safety-info-codec' => 'tests/Fuzz/participant-safety-info-codec.php',
        ],
    ],
];
