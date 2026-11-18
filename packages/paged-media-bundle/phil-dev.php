<?php

declare(strict_types=1);

use Phil\PagedMediaBundle\Infrastructure\Storage\DirectoryJobStore;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : déplacer les attributs dans un espace de noms Attribute (rupture de l’API publique).
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'path' => 'src/Application/AsDocumentTemplate.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/DocumentTemplate.php',
                    'src/Application/Port/JobStore.php',
                    'src/Application/Port/PdfStorage.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The bytes of a file held in memory, read by `JobCodec`: `InputFile::chunks()` yields them in one piece,
            // so keeping the last chunk gives the same bytes as joining them all.
            'Assignment' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$contents \\.= \\$chunk;',
                ],
            ],
            // The stat cache of PHP goes stale only when another process changes the directory: the functions of PHP
            // that change a file clear it themselves, so no test of one process sees the difference.
            'FunctionCallRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'clearstatcache\\(true, \\$this->directory\\);',
                ],
            ],
            // The directory was checked to exist just before and has just been resolved: `realpath()` gives `false` only
            // if it vanished in between, and `tempnam()` then refuses the empty path all the same.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$directory = \\(string\\) realpath\\(\\$this->directory\\);',
                ],
            ],
            // `tempnam()` gives a file of the system's directory only when the job directory cannot be written: the
            // refusal of that file is no more observable than the rename into the directory that then fails with the
            // same error, the file elsewhere being removed in both cases.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(false === \\$temporary \\|\\| \\\\dirname\\(\\$temporary\\) !== \\$directory\\) \\{',
                ],
            ],
            'Throw_' => [
                'ignore' => [DirectoryJobStore::class.'::temporary'],
            ],
            // A write or a rename that fails once the temporary file exists needs a full disk, a quota or a clash of two
            // random names: no test of one process produces it. The cleanup is the one of `BackupCipher`, whose failing
            // writer a test does produce.
            'UnwrapFinally' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(false === @file_put_contents\\(\\$temporary, \\$contents\\) .*',
                ],
            ],
            'IfNegation' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(file_exists\\(\\$temporary\\)\\) \\{',
                ],
            ],
            // A number read as a float: the return type `float` widens an integer, so the cast changes nothing.
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\\\is_int\\(\\$value\\) \\|\\| \\\\is_float\\(\\$value\\) \\? \\(float\\) \\$value : throw .*',
                ],
            ],
        ],
    ],
];
