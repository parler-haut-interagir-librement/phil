<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'deptrac' => [
        // The core is plain PHP; the adapter of phil/forms-bundle's port alone sees that bundle's domain, an optional
        // dependency, required in development and suggested.
        'layers' => [
            'PhilFormLogic' => '^Phil\\\\FormLogic\\\\(?!FormsBundle\\\\).*',
            'PhilFormLogicAdapter' => '^Phil\\\\FormLogic\\\\FormsBundle\\\\.*',
        ],
        'ruleset' => [
            'PhilFormLogic' => ['DefaultAllowed'],
            'PhilFormLogicAdapter' => ['PhilFormLogic', 'DefaultAllowed', 'PhilFormsBundleDomain'],
        ],
    ],
    'fuzz' => [
        // The JsonLogic reader on rules nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'json-logic-reader' => 'tests/Fuzz/json-logic-reader.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // A division and a remainder have two operands, as `Arithmetic` demands: the cast only tells PHPStan that
            // the second one is there.
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => ['.*\\(float\\) \\$second.*'],
            ],
            // An integral number is as much its floor as its ceiling or its rounding.
            'RoundingFamily' => [
                'ignoreSourceCodeByRegex' => ['.*floor\\(\\$number\\) === \\$number.*'],
            ],
            // The writing of a number as JavaScript does: each of these bounds is a value no branch before it lets through.
            // A negative number comes after zero; the plain notation with a point has fewer than 18 digits, so 21 is
            // never its limit; an exponent of 0 is plain notation; the shortest digits are found by the 16th decimal
            // at the latest; the shortest digits never end with a 0; the exponent is a number whatever its sign.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    '.*if \\(\\$number < 0\\) \\{.*',
                    '.*\\$exponent < 0 \\? .-. : .\\+..*',
                    '.*for \\(\\$precision = 0; \\$precision < 17; \\+\\+\\$precision\\).*',
                ],
            ],
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['.*0 < \\$point && \\$point <= 21.*'],
            ],
            'UnwrapRtrim' => [
                'ignoreSourceCodeByRegex' => ['.*rtrim\\(str_replace\\(.\\.., .., \\$mantissa\\), .0.\\).*'],
            ],
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\(int\\) \\$exponent \\+ 1.*'],
            ],
            // The two texts are looked at together for a character above U+FFFF, in whichever order.
            'Concat' => [
                'ignoreSourceCodeByRegex' => ['.*preg_match\\(./\\[\\\\x\\{10000\\}.*'],
            ],
            // Sets whose values nothing reads: only their keys, tested with `isset` or `array_keys`.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\$later\\[\\$name\\] = true;.*',
                    '.*\\$names\\[\\$(expression->name|name)\\] = true;.*',
                ],
            ],
        ],
    ],
    'js' => ['paths' => ['assets', 'tests/js', 'tests/Browser/App/assets']],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking', 'words' => ['JsonLogic', 'UTF-16']],
];
