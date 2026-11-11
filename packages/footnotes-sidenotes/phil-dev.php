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
            // The end of a reference is searched from anywhere inside its start tag, in `ReferenceMarkup::replace()`:
            // the length of the identifier in place of that of the tag lands inside the tag as well, after its `<sup`.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$at = self::end\\(\\$html, \\$start \\+ .*',
                ],
            ],
            // A number among the replacements of `strtr()`, in `NoteLabels::backlinkName()`, is written as its digits
            // with or without the cast, which tells PHPStan; the file of a sheet the package ships is there to read, and
            // the cast only serves the string|false of `file_get_contents()`.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\'%occurrence%\' => \\(string\\) \\$occurrence.*',
                    'return \\(string\\) file_get_contents\\(\\$this->path\\(\\)\\);',
                ],
            ],
        ],
    ],
    'docs' => [
        // The package is released from its own repository: its CHANGELOG uses every section.
        'public' => true,
        'words' => ['GCPM', 'sidenotes', 'WeasyPrint'],
    ],
    'fuzz' => [
        // The walk over stored documents and the scan of their references, on input nobody wrote.
        'targets' => [
            'footnotes-sidenotes' => 'tests/Fuzz/footnotes-sidenotes.php',
        ],
        'budget' => 20,
    ],
];
