<?php

declare(strict_types=1);

use Phil\AccessibilityCheck\Ujo\UjoReader;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // The constraint sits beside its validator, as Symfony pairs a Constraint and its ConstraintValidator and as
            // phil/svg-sanitizer places SafeSvg: an Attribute namespace would split the pair.
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'path' => 'src/Validator/AccessibleContent.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // A contrast ratio equal to its threshold to the last bit of a float is no pair of colours anyone writes:
            // `<` and `<=` cannot be told apart.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['if \\(\\$ratio < \\$required\\) \\{'],
            ],
            // The size matched by the expression is a numeric string, which multiplies as the float it is cast to.
            'CastFloat' => [
                'ignoreSourceCodeByRegex' => ['return \\(float\\) \\$match\\[\'size\'\\] \\* match.*'],
            ],
            // The two halves of the sRGB transfer function meet at 0.0031308, where both give 0.04045 to the last digit
            // a channel keeps: `<=` and `<` cannot be told apart.
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => ['return 255 \\* \\(\\$linear <= 0\\.0031308 \\?.*'],
            ],
            // The checks before `Block::fromArray()` only spare it the arrays that are plainly not blocks, and the
            // exceptions it would throw for them: it refuses them as well, and the reader then reads them as data.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['if \\(!\\\\is_array\\(\\$value\\) \\|\\| !\\\\is_string\\(\\$value\\[\'type\'\\].*'],
            ],
            'ReturnRemoval' => [
                'ignore' => [UjoReader::class.'::blockOf'],
            ],
        ],
    ],
    'deptrac' => [
        // The checker reads HTML through the DOM extension of PHP and, when it is installed, UJO through phil/ujo; the
        // constraint, the workflow guard and the command, each in its namespace, see the Symfony component they adapt
        // to.
        'layers' => [
            'PhilAccessibilityCheck' => '^Phil\\\\AccessibilityCheck\\\\(?!Validator\\\\|Workflow\\\\|Console\\\\).*',
            'PhilAccessibilityCheckBridge' => '^Phil\\\\AccessibilityCheck\\\\(?:Validator|Workflow|Console)\\\\.*',
        ],
        'ruleset' => [
            'PhilAccessibilityCheck' => ['DefaultAllowed', 'PhilUjo', 'Forbidden'],
            'PhilAccessibilityCheckBridge' => ['PhilAccessibilityCheck', 'PhilUjo', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // The HTML checker on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'html-checker' => 'tests/Fuzz/html-checker.php',
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
