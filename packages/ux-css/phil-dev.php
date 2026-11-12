<?php

declare(strict_types=1);

use Phil\UxCss\Color\Oklch;
use Phil\UxCss\Stylesheet\StylesheetBundler;
use Phil\UxCss\Token\Calc;
use Phil\UxCss\Token\TokenSheet;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['bin', 'src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The computation of calc() and the export of design tokens, on inputs nobody wrote, from the seeds of
        // tests/Fuzz/corpus/<target>.
        'targets' => [
            'calc' => 'tests/Fuzz/calc.php',
            'design-tokens' => 'tests/Fuzz/design-tokens.php',
        ],
    ],
    'docs' => [
        'public' => true,
        'words' => ['Sparkline', 'OKLCH', 'WCAG', 'importmap', 'DTCG', 'Béziers', 'lightbox', 'CSSOM'],
    ],
    'infection' => [
        'mutators' => [
            // Two tones reach the fixed 3.0 or 4.5 exactly only by an accident of floating point, which no lightness
            // written in CSS can be relied on to produce: `<` and `<=` judge every tone alike.
            'LessThan' => ['ignoreSourceCodeByRegex' => ['if \\(\\$ratio < \\$minimum\\) \\{']],
            // A number CSS writes is a numeric string, which the division turns into the same float without the cast.
            'CastFloat' => ['ignore' => [Oklch::class.'::fromCss']],
            // preg_replace() returns null only on a backtracking error, which these patterns without nested quantifiers
            // cannot reach: the cast only tells PHPStan so.
            'CastString' => ['ignore' => [StylesheetBundler::class.'::bundle', TokenSheet::class.'::fromCss']],
            // A custom property's name, as the sheet reads it, never holds a new line: with or without D, `$` judges every
            // name alike.
            'PregMatchRemoveFlags' => ['ignore' => [TokenSheet::class.'::tones', TokenSheet::class.'::roles']],
            // Every value that reaches resolveValue() is already trimmed, by the reading of the sheet or by the split of
            // the arguments; the trim keeps the method right on its own.
            'UnwrapTrim' => ['ignore' => [TokenSheet::class.'::resolveValue']],
            // The depth of the parentheses is only compared with zero, which counting down reaches at the same commas as
            // counting up.
            'PlusEqual' => ['ignore' => [TokenSheet::class.'::splitArguments']],
            // The memory of the properties already computed spares work, never changes a result: computing again gives
            // the same quantity.
            'ReturnRemoval' => ['ignore' => [Calc::class.'::property']],
            'InstanceOf_' => ['ignore' => [Calc::class.'::property']],
        ],
    ],
];
