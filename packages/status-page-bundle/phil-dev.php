<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'infection' => [
        'mutators' => [
            // A date is read by DateTimeImmutable::createFromFormat() once the shape has passed: a prefix before the year
            // moves the 19 characters the result is compared with, so it is refused whether the pattern starts at `^` or not.
            'PregMatchRemoveCaret' => [
                'ignoreSourceCodeByRegex' => ['.*\\$date = 1 === preg_match\\(\'/\\^.*'],
            ],
            // Without the D modifier, `$` also matches before a final line feed; createFromFormat() then refuses the
            // trailing line feed, so a date followed by one is refused either way.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['.*\\$date = 1 === preg_match\\(\'/\\^.*'],
            ],
            // The XML document is written to memory: saveXml() returns false only when it cannot allocate, a branch no test reaches.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => ['.*The feed could not be written\\..*'],
            ],
            // Two dates that are equal are the same instant: the feed shows the same text whichever of them it keeps.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['.*\\$candidate > \\$current \\? \\$candidate : \\$current.*'],
            ],
            // The file is read one byte past the limit to know it is too large: reading two bytes past it refuses the same files,
            // the length only bounds the memory.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*file_get_contents\\(\\$this->path, length: self::MAX_BYTES \\+ 1\\).*'],
            ],
        ],
    ],
    'fuzz' => [
        // Incidents and maintenances read from JSON nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'status-records' => 'tests/Fuzz/status-records.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside the fuzzer, which gives them what PHPStan does not know.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
