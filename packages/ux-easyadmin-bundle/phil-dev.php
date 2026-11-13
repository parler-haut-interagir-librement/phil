<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'words' => ['EasyAdmin']],
    // The served stylesheet is written by bin/build.php, the token files of phil/ux-css copied as they are: Biome reads
    // its source.
    'js' => ['paths' => ['assets/src']],
    'infection' => [
        'mutators' => [
            // phil/ux-css is required by the package, so it is always installed where the tests run: the `throw` that
            // tells it is missing is unreachable, and no test can observe its mutation.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$package = InstalledVersions::getInstallPath\\(.phil/ux-css.\\) \\?\\? throw new LogicException\\(.*',
                ],
            ],
        ],
    ],
];
