<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Geocoding/Geocoder.php',
                    'src/Geocoding/IpLocator.php',
                ],
            ],
            // à corriger : type natif manquant (ressource ou paramètre de fermeture) pour la couverture de types à 100 %.
            [
                'identifier' => 'typeCoverage.returnTypeCoverage',
                'path' => 'src/Geocoding/MaxMind/FileBytes.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // Limits declared as constants: mutating their declaration produces a mutant no reasonable test kills. The
            // 28-bit node of MaxMind is 7 bytes long: `substr($bytes, 4, 4)` returns the same 3 bytes as
            // `substr($bytes, 4, 3)`.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'private const int MAX_.*',
                    ': \\(\\(\\\\ord\\(\\$bytes\\[3\\]\\) & 0x0F\\) << 24\\) \\| \\$this->number\\(substr\\(\\$bytes, 4, 3\\)\\),',
                ],
            ],
            // See `IncrementInteger`.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'private const int MAX_.*',
                ],
            ],
            // The `0.0` fallback of `unpack()`, never returned.
            'OneZeroFloat' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\\\is_array\\(\\$value\\) && \\\\is_float\\(\\$value\\[1\\]\\) \\? \\$value\\[1\\] : 0\\.0;',
                ],
            ],
            // `filesize()` in `FileBytes`, just after a successful `is_file`, `is_readable` and `fopen`, returns an
            // integer.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$this->size = \\(int\\) filesize\\(\\$this->path\\);',
                ],
            ],
            // The upper bounds of the window of the Decoder and of the blocks of `FileBytes`: a read that ends right at
            // the end takes the general path and returns the same bytes.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$at >= 0 && \\$at \\+ \\$length <= \\\\strlen\\(\\$this->window\\)\\) \\{',
                    'if \\(isset\\(\\$this->blocks\\[\\$first\\]\\) && \\$at \\+ \\$length <= \\\\strlen\\(\\$this->blocks\\[\\$first\\]\\)\\) \\{',
                ],
            ],
            // The reload of the window of the Decoder: `blockAt()` returns a block and its real start, consistent even
            // for a wrong offset; the window is only useless.
            'Plus' => [
                'ignoreSourceCodeByRegex' => [
                    '\\[\\$this->windowStart, \\$this->window\\] = \\$this->bytes->blockAt\\(\\$this->start \\+ \\$offset\\);',
                ],
            ],
            // The shortcuts of reading (memorised IPv4 start, window of the Decoder, kept blocks of `FileBytes`):
            // without them, the same read takes the general path and returns the same bytes; only the cost changes.
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\$this->ipv4Start;',
                    'return substr\\(\\$this->window, \\$at, \\$length\\);',
                    'return substr\\(\\$this->blocks\\[\\$first\\], \\$at, \\$length\\);',
                ],
            ],
            // The memo of the Decoder: building it again gives the same result, only the cost changes.
            'AssignCoalesce' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\$this->data \\?\\?= new Decoder\\(.*\\);',
                ],
            ],
            // `fread()` on an open file in `FileBytes` only returns false on an input-output error no test causes.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\$this->blocks\\[\\$block\\] = \\(string\\) fread\\(\\$handle, self::BLOCK\\);',
                ],
            ],
            // The bound of the walk of the MaxMind tree: the case `$node === nodeCount` is already returned as null
            // just before.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$node < \\$metadata->nodeCount\\) \\{',
                ],
            ],
            // The fallbacks of `unpack()` in the Decoder: on a size checked just before, `unpack()` always returns the
            // expected array; the fallback branch is unreachable.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\\\is_array\\(\\$value\\) && \\\\is_float\\(\\$value\\[1\\]\\) \\? \\$value\\[1\\] : 0\\.0;',
                    'return \'0x\'\\.\\(\\\\is_array\\(\\$hex\\) && \\\\is_string\\(\\$hex\\[1\\]\\) \\? \\$hex\\[1\\] : \'\'\\);',
                ],
            ],
            // The position in the window of the Decoder: a wrong computation falls outside the window and the read goes
            // through `FileBytes`, which returns the same bytes.
            'Minus' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$at = \\$this->start \\+ \\$offset - \\$this->windowStart;',
                ],
            ],
            // The lower bound of the window of the Decoder: a read that does not take the shortcut goes through
            // `FileBytes` and returns the same bytes.
            'GreaterThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$at >= 0 && \\$at \\+ \\$length <= \\\\strlen\\(\\$this->window\\)\\) \\{',
                ],
            ],
            // A read before the window of the Decoder can only be a control or pointer byte, which never goes beyond
            // the window: the mutated condition stays false and the read goes through `FileBytes`.
            'LogicalAndAllSubExprNegation' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$at >= 0 && \\$at \\+ \\$length <= \\\\strlen\\(\\$this->window\\)\\) \\{',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The library is plain PHP; only the geocoding adapters see the rest of the world, the HTTP client and the
        // cache contracts.
        'layers' => [
            'PhilGeo' => '^Phil\\\\Geo\\\\(?!Geocoding\\\\Adapter\\\\).*',
            'PhilGeoGeocodingAdapter' => '^Phil\\\\Geo\\\\Geocoding\\\\Adapter\\\\.*',
        ],
        'ruleset' => [
            'PhilGeo' => ['DefaultAllowed'],
            'PhilGeoGeocodingAdapter' => ['PhilGeo', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
