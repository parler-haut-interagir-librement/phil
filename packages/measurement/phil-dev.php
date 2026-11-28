<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'infection' => [
        'mutators' => [
            // A zero denominator is refused first, and a zero numerator has no remainder to round: zero reads the same
            // as a positive number or a negative one.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*if \\(0 > \\$denominator\\) \\{.*',
                    '.*\\$negative = 0 > \\$this->numerator;.*',
                    // A whole negative quotient is a floor away from its fraction of one, and zero is its own floor.
                    '.*return 0 > \\$numerator && 0 !== \\$numerator % \\$denominator.*',
                    // A sum of zero is written 0/1 whichever way its sign is read.
                    '.*0 > \\$sum.*',
                    // The product of zero is zero whichever way its sign is read.
                    '.*return \\[0 > \\$this->numerator \\? -\\(int\\) \\$digits : \\(int\\) \\$digits, \\$scale\\];.*',
                ],
            ],
            // A whole negative quotient taken one lower leaves a remainder of one whole unit: the same value, compared as
            // exactly.
            'Modulus' => [
                'ignoreSourceCodeByRegex' => ['.*return 0 > \\$numerator && 0 !== \\$numerator % \\$denominator.*'],
            ],
            // The decimal of 18 significant digits is the same read from the exact digits or from the 18 available ones
            // trimmed of their zeros: the exact digits only spare the long division some steps.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['.*null !== \\$exact && \\$exact <= \\$available.*'],
            ],
            'LogicalAndAllSubExprNegation' => [
                'ignoreSourceCodeByRegex' => ['.*null !== \\$exact && \\$exact <= \\$available.*'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '.*null !== \\$exact && \\$exact <= \\$available.*',
                    // The common divisor of two positive integers is never zero, and a factor of zero zeroes the product
                    // whatever the other one is.
                    '.*return 0 === \\$left \\? 1 : \\$left;.*',
                    '.*private function product\\(int \\$a, int \\$b, int \\$c = 0, int \\$d = 0\\).*',
                    // Padding with one zero more only adds leading zeros, and with one less, the kept digits are read as
                    // zero when none is left.
                    '.*\\$digits = str_pad\\(\\$digits, \\$dropped \\+ 1, .*',
                    // The smallest fraction, 1/PHP_INT_MAX, is 1.08e-19: 18 zeros after the point show in 19 digits, in 18
                    // as in 20.
                    '.*\\$shown = \\$magnitude->toDecimal\\(19, RoundingMode::TowardsZero\\);.*',
                    // A product of two native integers has 38 digits at most: 5 columns of 9 hold them, 7 only add zeros.
                    '.*\\$columns = array_fill\\(0, 6, 0\\);.*',
                    // The first part of a key is the same cut in 2 parts or in 3.
                    '.*explode\\(\'-\', \\(string\\) \\$key, 2\\)\\[0\\].*',
                ],
            ],
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    // The places shifted are the scale of a number less the smaller of the two scales, never below zero: the
                    // floor only tells the type of the power an integer, and one under it is never reached.
                    '.*10 \\*\\* max\\(0, \\$places\\).*',
                    '.*return 0 === \\$left \\? 1 : \\$left;.*',
                    '.*private function product\\(int \\$a, int \\$b, int \\$c = 0, int \\$d = 0\\).*',
                    '.*\\$digits = str_pad\\(\\$digits, \\$dropped \\+ 1, .*',
                    '.*\\$shown = \\$magnitude->toDecimal\\(19, RoundingMode::TowardsZero\\);.*',
                    '.*\\$columns = array_fill\\(0, 6, 0\\);.*',
                ],
            ],
            // Whole digits are read as integers, so a leading zero or a unary minus on a numeric string changes nothing.
            'UnwrapLtrim' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$digits = ltrim\\(str_replace\\(\'\\.\', \'\', \\$magnitude->toDecimal\\(\\$scale, RoundingMode::HalfEven\\)\\), \'0\'\\);.*',
                    // Only the integer product of two numbers leads with zeros, and its kept digits are one lone zero.
                    '.*\\$digits = ltrim\\(\\$digits, \'0\'\\)\\.str_repeat.*',
                ],
            ],
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    // A string of digits is read as a number by arithmetic and by the plural rules alike.
                    '.*\\$digits\\[\\$position\\] = \\(string\\) \\(\\(int\\) \\$digits\\[\\$position\\] \\+ 1\\);.*',
                    '.*return \\[0 > \\$this->numerator \\? -\\(int\\) \\$digits : \\(int\\) \\$digits, \\$scale\\];.*',
                    '.*\\(int\\) \\$kept\\[-1\\] % 2.*',
                    '.*\\$category = \\(string\\) \\$symbols\\[\'plural\'\\]->format.*',
                    '.*mb_chr\\(\\$symbols\\[\'zero\'\\] \\+ \\(int\\) \\$digit, \'UTF-8\'\\).*',
                ],
            ],
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => [
                    // A string of digits is read as a number by the plural rules, as the number it writes.
                    '.*\$category = \(string\) \$symbols\[\'plural\'\]->format.*',
                ],
            ],
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$digits\\[\\$position\\] = \\(string\\) \\(\\(int\\) \\$digits\\[\\$position\\] \\+ 1\\);.*',
                    // ICU gives a plural category as a string, and an integer as a string of digits.
                    '.*\\$category = \\(string\\) \\$symbols\\[\'plural\'\\]->format.*',
                    '.*\\$written = \\(string\\) \\$symbols\\[\'integer\'\\]->format\\(\\(int\\) \\$integer\\);.*',
                    // PHP turns a key of digits into an integer whichever way it is cast, and the writer then refuses it.
                    '.*\\$preferences\\[\\$category\\]\\[\\(string\\) \\$usage\\]\\[\\(string\\) \\$region\\]\\[\\] = .*',
                    '.*\\$patterns\\[\\$width\\]\\[\\(string\\) \\$key\\] = \\$kept;.*',
                ],
            ],
            // A missing key reads as null with ??, so a document that is not an object gives the same refusal whichever
            // of the tests of its parts is required.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$categories = \\\\is_array\\(\\$data\\) && .*',
                    '.*\\$units = \\\\is_array\\(\\$data\\) && .*',
                ],
            ],
            // A list of one part joined as the locale joins them is that part.
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*return \\$written\\[0\\];.*'],
            ],
            // Whichever the order of the keys of one part, the units are listed by position.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*return \\$this->splitRounded\\(\\[\\$largest, \\.\\.\\.array_values\\(\\$smaller\\)\\], null\\);.*'],
            ],
            // The units key is created by the first unit added, a family defining one at least.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*\\$entry = \\$known \\?\\? \\[\'units\' => \\[\\]\\];.*'],
            ],
        ],
    ],
    'fuzz' => [
        // A measurement as a form or a feed gives it, and unit definitions from a configuration, from the seeds of
        // tests/Fuzz/corpus/measurement-*.
        'targets' => [
            'measurement-parse' => 'tests/Fuzz/measurement.php',
            'measurement-definitions' => 'tests/Fuzz/definitions.php',
        ],
    ],
];
