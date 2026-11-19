<?php

declare(strict_types=1);

use Phil\MimeReader\Internal\EncodedWords;
use Phil\MimeReader\Internal\TransferDecoder;
use Phil\MimeReader\MimeReader;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The reader on messages nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'mime-reader' => 'tests/Fuzz/mime-reader.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // `Bytes::find()` returns the first needle whatever the window: its growth factor and its overlap of one octet
            // more or less only change how many reads a far needle costs, since a needle found beyond the overlap starts
            // where the next window would find it.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\$window = min\(\$window \* 2.*',
                    // The reader asks for one range more than its budget allows to know that the multipart is cut: asking
                    // for two reads one more range that the loop never reaches.
                    '.*\$budget->left\(\) \+ 1\);',
                    // `Headers::parse()` trims every value and skips an empty line: where the header text ends, among the
                    // line breaks that follow the last field, changes nothing it reads.
                    '.*null !== \$crlf => .*',
                    '.*null !== \$lf => .*',
                    // A delimiter starts with `-` and a real one follows a line break: none starts one octet after a refused
                    // one.
                    '.*\$at = \$found \+ 1;',
                    // The tokens of `preg_split()` number one more than a multiple of five: the last text is the only one
                    // with fewer than five tokens after it, whichever bound of that comparison.
                    '.*if \(\$i \+ 4 < \$count\).*',
                    // A name that reaches the sections is extended, or has an index: `preg_match()` then sets the index group,
                    // empty or not, and the default after `??` is never read.
                    '.*\(int\) \(\$match\[2\] \?\? 0\).*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\$window = min\(\$window \* 2.*',
                    '.*min\(\$window \+ \$length - 1.*',
                    '.*if \(\$i \+ 4 < \$count\).*',
                    '.*\(int\) \(\$match\[2\] \?\? 0\).*',
                    '.*null !== \$crlf => .*',
                    '.*null !== \$lf => .*',
                    // `Base64::groups()` with a tail under two decodes the whole alphabet it is given, as with none.
                    '.*Base64::groups\(substr\(\$chunk, 0, \$usable\), 0\).*',
                    // A cut past the end of the data leaves an empty carry, as no cut does.
                    '.*return false === \$equals \? 0 :.*',
                    // Only a comment above zero is read: the count before the first one is never compared to anything else.
                    '.*\$comment = 0;',
                ],
            ],
            'Minus' => [
                'ignoreSourceCodeByRegex' => ['.*min\(\$window \+ \$length - 1.*'],
            ],
            'Plus' => [
                'ignoreSourceCodeByRegex' => ['.*null !== \$crlf => .*'],
            ],
            'LessThan' => [
                // The tokens of `preg_split()` number one more than a multiple of five, so `<=` adds no pass. An extra pass
                // at the end of a base64 or quoted-printable body has nothing to decode and nothing to carry. One range more
                // than the budget allows is never read: the loop over them stops at the first it cannot take.
                'ignore' => [
                    EncodedWords::class.'::decode',
                    TransferDecoder::class.'::base64',
                    TransferDecoder::class.'::quotedPrintable',
                    MimeReader::class.'::bodies',
                ],
            ],
            // A value without any encoded word is read by the general path as the fast path reads it.
            'ReturnRemoval' => [
                'ignore' => [EncodedWords::class.'::decode'],
            ],
            // Going on after the budget is spent adds no child, as stopping does.
            'Break_' => [
                'ignore' => [MimeReader::class.'::part'],
            ],
            // `(.*)` with the `s` flag takes the end of the text whole, `$` or not.
            'PregMatchRemoveDollar' => [
                'ignoreSourceCodeByRegex' => ['.*preg_match\("/\^\(\[\^\'\]\*\)\'.*'],
            ],
            // `preg_replace()` returns a string unless a pattern is invalid, and these are not; `fwrite()` that fails
            // adds `false`, which is 0.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\(string\) preg_replace\(.*'],
            ],
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\(int\) fwrite\(.*'],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['multiparts', 'unserialized']],
];
