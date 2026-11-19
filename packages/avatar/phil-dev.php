<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'fuzz' => [
        // The initials of names nobody wrote, and the avatars drawn from them, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'initials' => 'tests/Fuzz/initials.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // No channel, a whole number over 255, is exactly 0.04045.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['return \\$value <= 0\\.04045 .*'],
            ],
            // No background of 8-bit channels has a contrast of exactly 4.5 with an ink, as a ratio of floats.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['if \\(max\\(\\$background->contrastWith.*'],
            ],
            // A name of exactly 1024 bytes is cut where it already ends.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['if \\(\\\\strlen\\(\\$name\\) > self::MAX_NAME_BYTES\\) \\{'],
            ],
            // The cluster is read whole by `.*`: the dot-all flag and the end anchor change nothing for one cluster, which
            // never holds a line break after a letter.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => ['.*\\(\\.\\*\\)\\$/us.*'],
            ],
            'PregMatchRemoveDollar' => [
                'ignoreSourceCodeByRegex' => ['.*\\(\\.\\*\\)\\$/us.*'],
            ],
            // The count of letters is 1 to 3 whatever the clamp: initials hold at most three clusters.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['\\$letters = min\\(3, max\\(1, .*'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['\\$letters = min\\(3, max\\(1, .*'],
            ],
            // The assertion only tells static analysis the shape of unpack(): both halves are true.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => ['\\\\assert\\(\\\\is_array\\(\\$word\\) && .*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['WCAG', 'maqaf', 'Thaana', 'Mandaic', 'Adlam', 'محمد']],
];
