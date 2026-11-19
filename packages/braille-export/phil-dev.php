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
    'docs' => [
        'public' => true,
        'translations' => 'blocking',
        'words' => ['BANA', 'BRF', 'brf', 'CBFU', 'DAISY', 'embosser', 'embossers', 'liblouis', 'linearized', 'notetakers', 'PEF', 'pef', 'runover', 'UEB', 'français', 'uniformisé', 'intégral', 'abrégé', 'subtag'],
    ],
    'infection' => [
        'mutators' => [
            // The room of a line is 3 cells at least for every translator that writes page numbers of a few cells (12 cells,
            // a page number of 6 at most, its gap of 3): the maximums below only keep a translator that writes page numbers
            // of 7 cells or more from giving a negative indentation or padding, and they change nothing before that.
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => [
                '.*max\\(0, \\$sink->width\\(\\) - self::MIN_TEXT\\)\\);',
                '.*max\\(0, \\$before\\)\\).*',
                '.*max\\(0, \\$cells - Braille::length\\(\\$row\\)\\)\\);',
                // The constant JSON of the signs has no nesting: the depth is a bound, not a behaviour.
                '.*json_decode\\(\\$json, true, 2, .*',
            ]],
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => [
                '.*max\\(0, \\$sink->width\\(\\) - self::MIN_TEXT\\)\\);',
                '.*max\\(0, \\$before\\)\\).*',
                '.*max\\(0, \\$cells - Braille::length\\(\\$row\\)\\)\\);',
                '.*json_decode\\(\\$json, true, 2, .*',
                // No hyphen to cut after is told by a cut of 0 or less: only `> 0` reads it.
                '.*\\$cut = 0;',
            ]],
            // A cast that only tells PHPStan the type: `preg_replace` gives null only on an invalid UTF-8 text, scrubbed
            // before, and PHP turns a numeric string key into the integer of the same value.
            'CastString' => ['ignoreSourceCodeByRegex' => [
                '.*\\$text = \\(string\\) preg_replace\\(.*',
                '.*\\$cells\\[\\(string\\) \\$character\\] = .*',
            ]],
            // The dots of a cell are digits: arithmetic on a numeric string gives the same number.
            'CastInt' => ['ignoreSourceCodeByRegex' => ['.*\\(int\\) \\$dot - 1.*']],
        ],
    ],
    'fuzz' => [
        // Any text, in French and in English, laid out as each kind of block on a narrow page: rows of braille that fit.
        'targets' => ['layout' => 'tests/Fuzz/layout.php'],
        'budget' => 60,
    ],
];
