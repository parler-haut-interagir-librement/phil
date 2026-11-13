<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // The parser is one recursive descent over one cursor: each method reads a construct of the grammar, and
            // the class sums them. Splitting it would hand the cursor and the depth to a second class that every
            // construct calls back; each method stays under the bar of 25.
            [
                'identifier' => 'complexity.classLike',
                'path' => 'src/Internal/Parser.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The digits left after the first one of a number taken as an argument are only read as a number again:
            // nothing reports their offset nor reads their raw text.
            'DecrementInteger' => ['ignoreSourceCodeByRegex' => [
                '\$this->tokens\[\$this->position\] = new Token\(Token::NUMBER, .*',
                // A macro body is read at a `#` that is followed by a number, or the definition is refused: the digit is
                // 0 or -1 for the same refusal.
                '.*\$digit = \$number->is\(Token::NUMBER\).*',
                // `braced` reads after a `^` or a `_`: the position is at least 1, and the maximum with 0 never applies.
                '.*max\(0, \$this->position - 1\).*',
                // Only the first byte of a token is ever where a problem is reported, in a text read for the chemistry:
                // the offsets of the bytes after it are not read.
                '.*array_push\(\$offsets, \.\.\.array_map.*',
                '.*\$offsets\[\] = \$token->offset \+ min\(.*',
            ]],
            'IncrementInteger' => ['ignoreSourceCodeByRegex' => [
                '\$this->tokens\[\$this->position\] = new Token\(Token::NUMBER, .*',
                '.*array_push\(\$offsets, \.\.\.array_map.*',
            ]],
            'Plus' => ['ignoreSourceCodeByRegex' => [
                '\$this->tokens\[\$this->position\] = new Token\(Token::NUMBER, .*',
                '.*array_push\(\$offsets, \.\.\.array_map.*',
                '.*\$offsets\[\] = \$token->offset \+ min\(.*',
            ]],
            'Minus' => ['ignoreSourceCodeByRegex' => [
                '.*array_push\(\$offsets, \.\.\.array_map.*',
                '.*\$offsets\[\] = \$token->offset \+ min\(.*',
            ]],
            'UnwrapSubstr' => ['ignoreSourceCodeByRegex' => ['\$this->tokens\[\$this->position\] = new Token\(Token::NUMBER, .*']],
            // A colour is trimmed before it is matched: `$` before a final line break is the only thing `D` changes,
            // and no line break is left to match.
            'PregMatchRemoveFlags' => ['ignoreSourceCodeByRegex' => ['.*/D\', \$color.*']],
            // A command is one name of letters or one character that is not a letter: the pattern for a name matches
            // the same ones with or without its anchors.
            'PregMatchRemoveCaret' => ['ignoreSourceCodeByRegex' => ['.*preg_match\(\'/\^\[A-Za-z\]\+\$/\', \$token->value.*']],
            'PregMatchRemoveDollar' => ['ignoreSourceCodeByRegex' => ['.*preg_match\(\'/\^\[A-Za-z\]\+\$/\', \$token->value.*']],
            // PHP reads a numeric string as the same array key, and compares it as the same number.
            'CastInt' => ['ignoreSourceCodeByRegex' => [
                '.*\$arguments\[\(int\) \$token->value\[0\]\].*',
                '.*\$digit = \$number->is\(Token::NUMBER\).*',
            ]],
            // A number multiplied by 255 is the same one whether it is cast to a float before or not.
            'CastFloat' => ['ignoreSourceCodeByRegex' => ['.*\(int\) round\(\(float\) \$red \* 255\).*']],
            // The macros being expanded are only looked up by `isset`, which a false value satisfies too.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['.*\$active \+ \[\$token->value => true\].*']],
            // The nesting of braces in a text is only compared with zero: counting it down instead of up ends at the
            // same brace.
            'PlusEqual' => ['ignoreSourceCodeByRegex' => ['\$level \+= match \(\$token->type\) \{']],
        ],
    ],
    'fuzz' => [
        // The converter on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'latex-mathml' => 'tests/Fuzz/latex-mathml.php',
        ],
    ],
];
