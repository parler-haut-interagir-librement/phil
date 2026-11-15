<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        // The name phil/ux-easyadmin-bundle gives the administration it dresses.
        'words' => ['easyadmin'],
    ],
    'infection' => [
        'mutators' => [
            // The file was found a readable regular file of 256 KiB at most just before: reading it gives a string, and
            // the cast only serves the string|false of the signature.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\$bodies\[\$locale\] = \(string\) file_get_contents\(\$path\);',
                ],
            ],
        ],
    ],
];
