<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // `isset` is true for a key whose value is `false`: the identifiers of the regions only need to be keys.
            'TrueValue' => ['ignoreSourceCodeByRegex' => ['.*\\$this->regionIds\\[\\$region->id\\] = true;.*']],
            // The first element of the split is the same whatever the limit: the parameters after the first `;` are
            // not read.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*explode\(\';\', \$mediaType, 2\).*',
                    // A setting whose value holds a `:` is no valid setting: it is dropped with two parts or with three.
                    // The character after `NOTE` is a space or a tab, which the left trim that follows removes.
                    '\$opening = ltrim\(substr\(.*',
                    '\$parts = explode\(\':\', \$setting, 2\);',
                    // The hours of a duration are matched whenever its minutes or seconds are, and a bare `PT` is refused.
                    '.*\$value = \(int\) \(\$match\[1\] \?\? 0\).*',
                ],
            ],
            // The patterns of the lines of a document run on text that holds no line break, which `D` is there to
            // refuse at the end of a pattern: `$` and `\z` read the same line.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    '.*preg_match\(\'/\[\\\\r\\\\n\]/\', \$id\).*',
                    '.*preg_match\(\'/\^NOTE\(\?:\[ \\\\t\]\|\$\)/D\', \$line\).*',
                    '.*preg_match\(\'/\^\(STYLE\|REGION\)\[ \\\\t\]\*\$/D\'.*',
                    '.*preg_match\(\'/\^WEBVTT.*',
                    '.*preg_match\(\'/\^\(\[\\\\d:\.\]\{1,20\}\)\[ \\\\t\]\*-->.*',
                    '.*preg_match\(\'/\^\\\\s\*\\\\d\{1,9\}\\\\s\*\$/D\'.*',
                    '.*preg_match\(\'/\^\[\^\\\\s\]\{1,200\}\$/D\', \$parts\[1\]\).*',
                    // A class name holds no line break, and a language tag is trimmed before it is read.
                    '.*\$classes = array_values\(array_filter\(.*',
                    '.*\'lang\' === \$token->name && 1 === preg_match.*',
                    '.*preg_match\(\'/\^\(\\\\/\?\)\(\[A-Za-z\]\+\).*',
                    // The text is trimmed before the pattern, which then holds no line break at its end.
                    '.*preg_match\(\'/\^\(\?:\(\\\\d\{1,4\}\):\)\?\(\[0-5\]\?\\\\d\):.*trim\(\$text\).*',
                ],
            ],
            // `explode()` and `array_values()` readers below read a list and the same values under other keys alike.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*\$classes = array_values\(array_filter\(.*'],
            ],
            // `preg_split()` drops the empty parts, the ones the trim would have left at each end.
            'UnwrapTrim' => [
                'ignoreSourceCodeByRegex' => ['.*preg_split\(\'/\[ \\\\t\]\+/\', trim\(\$text.*'],
            ],
            // An identifier that opens as NOTE, STYLE or REGION is refused by `Cue` before the pattern is read at its
            // end; a cue at an instant is shown from that instant by its first test.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\? \$this->start === \$from \|\| \(\$this->start < \$from && \$from < \$this->end\).*',
                    // `isNumber()` is asked of a line that exists.
                    '.*return \$index < \$this->count && 1 === preg_match\(\'/\^\\\\s\*\\\\d\{1,9\}.*',
                ],
            ],
            // The `<` tag arm of `fromSrt()` escapes the text as its default arm does.
            'MatchArmRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*CueToken::TEXT => self::escape\(\$token->value\),.*'],
            ],
            // `array_pop()` of an open tag found by `in_array()` is a string.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['.*\$name = \(string\) array_pop\(\$open\);.*'],
            ],
            // The first group of a tag name never matches the empty string; `preg_split()` never returns an empty list.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\'\' === \$match\[3\] \? \[\] : array_values.*',
                    // The first element of a timecode, read as an integer, is its hours: `$match[0]` begins with them.
                    '.*return \(int\) \$match\[1\] \* self::HOUR \+ \(int\) \$match\[2\] \* self::MINUTE \+ \(int\) \$match\[3\] \* 1000$',
                    // The block of a note or a definition has its first line read before the end is sought, and that line
                    // holds no `-->` for a definition, and always fails the same way in a note.
                    '.*\$end = \$this->blockEnd\(\$first \+ 1\);.*',
                    // The hours of a duration are matched whenever its minutes or seconds are, and a bare `PT` is refused.
                    '.*\$value = \(int\) \(\$match\[1\] \?\? 0\).*',
                    // The first value of the loop is never read before it is set.
                    '.*\$nextEnd = 0;.*',
                    // `/` of two integers that divide is an integer: both arms of the test give the same number.
                    '.*return 0 === \$milliseconds % 1000 \? intdiv.*',
                ],
            ],
            'Modulus' => [
                'ignoreSourceCodeByRegex' => ['.*return 0 === \$milliseconds % 1000 \? intdiv.*'],
            ],
            // The numbers are numeric strings that the arithmetic reads as integers.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return \(int\) \$match\[1\] \* self::HOUR.*',
                    '.*\+ \(int\) str_pad\(.*',
                    '.*\$value = \(int\) \(\$match\[1\] \?\? 0\).*',
                ],
            ],
            // The only line of the array is returned by `preg_split()`: it never returns an empty list.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*return false === \$lines \|\| \[\] === \$lines.*'],
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['IMSC', 'SMPTE', 'TTML']],
    'fuzz' => [
        // Each reader on documents nobody wrote, from the seeds of tests/Fuzz/corpus/<target>: whatever it reads, it
        // returns what it read or throws an exception of the library.
        'targets' => [
            'webvtt' => 'tests/Fuzz/webvtt.php',
            'srt' => 'tests/Fuzz/srt.php',
            'json-transcript' => 'tests/Fuzz/json-transcript.php',
            'json-chapters' => 'tests/Fuzz/json-chapters.php',
            'html-transcript' => 'tests/Fuzz/html-transcript.php',
            'cue-text' => 'tests/Fuzz/cue-text.php',
        ],
    ],
    'deptrac' => [
        // The HTML transcripts are read with the HTML5 parser of PHP's DOM extension, which is outside the default and
        // falls in the Forbidden layer, as the other readers of the DOM extension allow it.
        'ruleset' => [
            'PhilTimedText' => ['DefaultAllowed', 'PhilUrl', 'Forbidden'],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
];
