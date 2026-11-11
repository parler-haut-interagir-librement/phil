<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The relative path of a font file in `LicenceRegister::scan()`: where the directory separator is already
            // `/`, replacing it by `/` changes nothing; it only counts on Windows, where the suite does not run.
            'UnwrapStrReplace' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$path = str_replace\\(\\\\DIRECTORY_SEPARATOR, \'/\', substr\\(\\$file->getPathname\\(\\), \\$root\\)\\);',
                ],
            ],
        ],
    ],
];
