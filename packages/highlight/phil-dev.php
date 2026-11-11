<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        // The Web Content Accessibility Guidelines, by the name the W3C gives them.
        'words' => ['WCAG'],
    ],
    'phpstan' => [
        // The worker-mode page runs inside FrankenPHP, which gives it `frankenphp_handle_request()`, unknown to PHPStan.
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Worker/*', 'tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'path' => 'src/Language.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The memory of the searches of `Markdown::find()`: a mark searched from a smaller point, found or not,
            // holds for any greater point; searching again returns the same position. Only the cost changes, which the
            // robustness test (outside mutation) measures.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$known = \\$memo\\[\\$needle\\] \\?\\? -1;',
                    // See `IncrementInteger`: the end of an annotation is its line index plus at least 1, so a start
                    // of -1, 0 or 1 gives the same farthest end.
                    '\\$until\\[\\$mark->value\\] = max\\(\\$until\\[\\$mark->value\\] \\?\\? 0, \\$index \\+ \\$length\\);',
                ],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // `LineRanges::parse()` cuts the text into one more part than it reads, only to know there are too
                    // many; any greater limit finds the same parts too many.
                    '\\$parts = explode\\(\',\', \\$ranges, self::MAX_RANGES \\+ 1\\);',
                    // `HtmlFormatter::annotate()`: an annotation ends at its line index plus its length, at least 1;
                    // the farthest end starting from 0 or from 1 is the same.
                    '\\$until\\[\\$mark->value\\] = max\\(\\$until\\[\\$mark->value\\] \\?\\? 0, \\$index \\+ \\$length\\);',
                ],
            ],
            // See `DecrementInteger`.
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$known = \\$memo\\[\\$needle\\] \\?\\? -1;',
                ],
            ],
            // See `DecrementInteger`.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$known < \\$from\\) \\{',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // The reader of line ranges and the lines of a block with their annotations, on inputs nobody wrote, from the
        // seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'line-ranges' => 'tests/Fuzz/line-ranges.php',
            'line-annotations' => 'tests/Fuzz/line-annotations.php',
        ],
    ],
    // The page `composer bench:worker phil/highlight` measures, served by FrankenPHP in worker mode.
    'bench' => [
        'worker' => ['script' => 'tests/Worker/worker.php', 'requests' => 1000, 'warmup' => 50],
    ],
];
