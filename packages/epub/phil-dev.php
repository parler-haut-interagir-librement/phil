<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The pattern of the media type already asks for "audio/" or "video/" at its start: the slash of the prefix
            // test repeats it.
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*str_starts_with\\(\\$mediaType, \\$kind->value\\.\'/\'\\).*'],
            ],
            // The four bytes "OggS" that start the substring cannot hold the eight bytes of "OpusHead": starting at the
            // second byte finds the same marker.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*str_contains\\(substr\\(\\$bytes, 0, 64\\), \'OpusHead\'\\).*'],
            ],
            // The largest page number starts from zero in `max()`: a label that is no number counting for -1, or for 0,
            // gives the same one; and a numeric string compares as its integer, so the cast of the comparison only
            // repeats the one that follows.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*self::isNumber\\(\\$page\\[1\\]\\) \\? \\(int\\) \\$page\\[1\\] : 0.*'],
            ],
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$sum \\+= \\(int\\) \\$digit \\* .*',
                    '.*self::isNumber\\(\\$label\\) && \\(int\\) \\$label > 0.*',
                ],
            ],
            // The compression of each entry of an EPUB: libzip compresses by default and stores uncompressed an entry
            // the compression would enlarge, which is the case of the twenty-byte `mimetype` file. Removing the call
            // gives the same archive; it stays to say what the specification requires.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$zip->setCompressionName\\(.*',
                ],
            ],
        ],
    ],
    'docs' => ['public' => true, 'words' => ['IDPF', 'ONIX', 'SMIL', 'reflowable', 'misnested']],
    'phpstan' => [
        'ignoreErrors' => [
            // à corriger : complexité cognitive au-dessus de 25, à découper : Assembler::linker() (29), XhtmlSerialization::element() (41).
            [
                'identifier' => 'complexity.functionLike',
                'paths' => [
                    'src/Internal/Assembler.php',
                    'src/Internal/XhtmlSerialization.php',
                ],
            ],
            // The font obfuscation of EPUB 3: the IDPF algorithm requires the SHA-1 key of the unique identifier of the
            // publication, which every EPUB reader computes again. It is neither a secret nor a security digest, and no
            // other algorithm would give a readable file.
            [
                'identifier' => 'disallowed.function',
                'message' => '#^Calling hash\\(\\) is forbidden, use hash\\(\\) with at least SHA-256#',
                'path' => 'src/Internal/FontObfuscation.php',
            ],
        ],
    ],
    'deptrac' => [
        // The writer reads HTML through the DOM extension of PHP, which is outside the default, and renders UJO
        // documents when the optional phil/ujo is installed.
        'ruleset' => [
            'PhilEpub' => ['DefaultAllowed', 'PhilUjo', 'Forbidden'],
        ],
    ],
];
