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
                    'src/Font/Font.php',
                    'src/Gotenberg/Signing/CertificateSource.php',
                    'src/Gotenberg/Signing/TimestampAuthority.php',
                    'src/Gotenberg/Stage/Stage.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The Gotenberg client: the size of the chunks of a file sent (1 MiB) only changes the memory, not the
            // bytes sent; the multipart boundary is the hash of 16 random bytes, as unpredictable with 15 or 17. The
            // bound of a group of format 12 of `TrueTypeFont`: U+110000 is not a code point, `mb_str_split()` never
            // produces it, and the extra entry is never read.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'public function chunks\\(int \\$size = 1_048_576\\): Generator',
                    '\\$this->boundary = \\$boundary \\?\\? \'phil-\'\\.hash\\(\'xxh128\', random_bytes\\(16\\)\\);',
                    'for \\(\\$codePoint = \\$first, \\$last = min\\(\\$this->uint32\\(\\$at \\+ 4\\), 0x10FFFF\\); \\$codePoint <= \\$last; \\+\\+\\$codePoint\\) \\{',
                ],
            ],
            // See `IncrementInteger`: the chunks and the boundary of the Gotenberg client. The smallest length of a stream
            // `Document` compresses: a length is never negative, so -1 compresses as much as the default 0.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'public function chunks\\(int \\$size = 1_048_576\\): Generator',
                    '\\$this->boundary = \\$boundary \\?\\? \'phil-\'\\.hash\\(\'xxh128\', random_bytes\\(16\\)\\);',
                    'private function stream\\(string \$entries, string \$data, int \$smallest = 0\\): string',
                ],
            ],
            // The Paeth predictor of `Image`: when the distances to the left and up are equal without the left pixel
            // winning, left and up hold the same value, and the mutant returns the same. The threshold of the sRGB
            // curve of `SrgbProfile`: no sampled point (a multiple of 1/1023) is exactly 0.04045, so `<` and `<=` give
            // the same curve.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$toLeft <= \\$toUp && \\$toLeft <= \\$toUpLeft\\) \\{',
                    '\\$linear = \\$encoded <= 0\\.04045 \\? \\$encoded / 12\\.92 : .*',
                ],
            ],
            // The members of a JSON object read by the Gotenberg client: PHP turns a numeric key into an integer on its
            // own, the cast changes nothing at run time and only serves PHPStan. `gzcompress()` at the constant level 9
            // never returns false in `Document`: the cast only serves PHPStan.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$members\\[\\(string\\) \\$key\\] = new self\\(\\$value, \\$this->path\\.\'\\.\'\\.\\$key\\);',
                    '\\$data = \\(string\\) gzcompress\\(\\$data, 9\\);',
                ],
            ],
            // The secret read from a vault by `VaultCertificateSource`: `??` returns null for an index of a string or a
            // number, so the second `is_array()` does not change the result; it stays for PHPStan.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$data = \\\\is_array\\(\\$json\\) && \\\\is_array\\(\\$json\\[\'data\'\\] \\?\\? null\\) \\? \\$json\\[\'data\'\\]\\[\'data\'\\] \\?\\? null : null;',
                ],
            ],
            // `isset()` is true for a key whose value is false: `Document` keeps the names of the fields placed and the
            // fonts a form types in as keys, so the value stored does not matter. The last line of a paragraph cut by
            // `Layout::paragraph()` always holds more than the width, as `Document::wrap()` broke the line there, so
            // forcing the ellipsis changes nothing.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$this->formFonts\\[\\$font\\] = true;',
                    '\\$this->fieldNames\\[\\$field->name\\] = true;',
                    '\\$kept\\[\\$lines - 1\\] = self::cut\\(.*, true\\);',
                ],
            ],
            // The colour components of `/MK`: the text before the last space is the same for the fill and the stroke
            // operator, `rg` and `RG` having the same length.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$components = static fn \\(Color \\$color\\): string => .*',
                ],
            ],
            // Loops that stop at the first item that no longer fits: the next ones sit lower still, so going on
            // changes nothing but the time it takes.
            'Break_' => [
                'ignoreSourceCodeByRegex' => [
                    'break;',
                ],
            ],
            // `format('N')` is a numeric string, which PHP subtracts as a number.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$offset = \\(\\(int\\) \\$month->first\\(\\)->format\\(\'N\'\\) - \\$this->firstDay \\+ 7\\) % 7;',
                ],
            ],
            // Trimming the whole text first changes nothing: the characters are trimmed again once cut. The width of a
            // text is the sum of the widths of its characters, whatever their order, so an ellipsis put before them
            // measures the same as one put after.
            'UnwrapRtrim' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$characters = mb_str_split\\(rtrim\\(\\$text\\)\\);',
                ],
            ],
            'Concat' => [
                'ignoreSourceCodeByRegex' => [
                    'while \\(\\[\\] !== \\$characters && \\$font->width\\(implode\\(\'\', \\$characters\\)\\.self::ELLIPSIS, \\$size\\) > \\$width\\) \\{',
                ],
            ],
            // A glyph of `TrueTypeFont` whose end equals its start: `substr(…, 0)` already returns the empty string.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    'return \\$end > \\$start \\? substr\\(\\$this->bytes, \\$this->table\\(\'glyf\'\\) \\+ \\$start, \\$end - \\$start\\) : \'\';',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The library is plain PHP; only the Gotenberg client sees the rest of the world, the HTTP client contracts, and
        // phil/url reads the addresses it sends.
        'layers' => [
            'PhilPagedMedia' => '^Phil\\\\PagedMedia\\\\(?!Gotenberg\\\\).*',
            'PhilPagedMediaGotenberg' => '^Phil\\\\PagedMedia\\\\Gotenberg\\\\.*',
        ],
        'ruleset' => [
            'PhilPagedMedia' => ['DefaultAllowed'],
            'PhilPagedMediaGotenberg' => ['PhilPagedMedia', 'PhilUrl', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'docs' => [
        'public' => true,
        'words' => ['autotag', 'CMYK', 'Factur', 'IDAS', 'subsetting', 'Weasy', 'WeasyPrint'],
    ],
];
