<?php

declare(strict_types=1);

use Phil\Slides\Infrastructure\DependencyInjection\SlidesServicesPass;

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, which gives it `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // The stylesheet is a file of the package, always there: file_get_contents() never gives false for it,
            // so the string it gives is the same with or without the cast.
            'CastString' => ['ignoreSourceCodeByRegex' => [
                '.*\\(string\\) file_get_contents\\(self::STYLESHEET\\).*',
                // An array key read as an integer or as its string is the same key: the cast only makes the name a string for the list.
                '.*\\$kept\\[\\(string\\) \\$name\\].*',
            ]],
            // The identifier of a note block is read by no renderer: its order of parts changes nothing in the HTML.
            'Concat' => ['ignoreSourceCodeByRegex' => ['.*\'phil-slides-note-\'\\.\\$index.*']],
            // Without Twig the pass has nothing more to remove: removing a definition already removed is no error.
            'ReturnRemoval' => ['ignore' => [SlidesServicesPass::class.'::process']],
        ],
    ],
    'fuzz' => [
        // The directives of a note, read from bytes nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'comment-directives' => 'tests/Fuzz/comment-directives.php',
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['Marp']],
];
