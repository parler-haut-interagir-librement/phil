<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // The constraints sit beside their validators, as Symfony pairs a Constraint and its ConstraintValidator and
            // as phil/svg-sanitizer places SafeSvg: an Attribute namespace would split the pairs.
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'paths' => ['src/Validator/MinimumStrength.php', 'src/Validator/NotCompromised.php'],
            ],
            // The Pwned Passwords range API is defined on SHA-1: the hash of a password is its address in the breach
            // corpus, by k-anonymity, never a protection of it. Nothing of the library stores a password with SHA-1.
            [
                'identifier' => 'disallowed.function',
                'paths' => ['src/Breach/Adapter/PwnedPasswordsSource.php', 'src/Breach/InMemoryBreachSource.php'],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The bounds of the walks from each position: one more step reaches a position from which no repeat, no
            // sequence of three and no walk of three keys fits, so it finds nothing.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'while \\(\\$i < \\$count - 1\\) \\{',
                    'while \\(\\$i < \\$count - 2\\) \\{',
                    // A base that fits only once never repeats: trying it only costs time.
                    'for \\(\\$length = 1; \\$start \\+ 2 \\* \\$length <= \\$count; \\+\\+\\$length\\) \\{',
                ],
            ],
            'Minus' => [
                'ignoreSourceCodeByRegex' => [
                    'while \\(\\$i < \\$count - 1\\) \\{',
                ],
            ],
            'LessThan' => [
                'ignoreSourceCodeByRegex' => [
                    'while \\(\\$i < \\$count - 1\\) \\{',
                    'while \\(\\$i < \\$count - 2\\) \\{',
                ],
            ],
            // A date is at most ten characters, a four-digit year, a month, a day and two separators: a longer part is
            // read and refused.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'for \\(\\$j = \\$i; \\$j < min\\(\\$count, \\$i \\+ 10\\); \\+\\+\\$j\\) \\{',
                ],
            ],
            // A cover with as many patterns that costs more is overwritten right after: removing it first changes nothing.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$other > \\$length && \\$otherGuesses >= \\$guesses\\) \\{',
                ],
            ],
        ],
    ],
    'deptrac' => [
        // The estimator, the generators and the breach checker are plain PHP; only the HTTP source sees the HTTP client
        // contracts, and only the constraints, under Validator, see symfony/validator.
        'layers' => [
            'PhilPasswords' => '^Phil\\\\Passwords\\\\(?!Validator\\\\|Breach\\\\Adapter\\\\).*',
            'PhilPasswordsBreachAdapter' => '^Phil\\\\Passwords\\\\Breach\\\\Adapter\\\\.*',
            'PhilPasswordsValidator' => '^Phil\\\\Passwords\\\\Validator\\\\.*',
        ],
        'ruleset' => [
            'PhilPasswords' => ['DefaultAllowed', 'Forbidden'],
            'PhilPasswordsBreachAdapter' => ['PhilPasswords', 'DefaultAllowed', 'Forbidden'],
            'PhilPasswordsValidator' => ['PhilPasswords', 'DefaultAllowed', 'Forbidden'],
        ],
    ],
    'fuzz' => [
        // Each parser on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'range-response' => 'tests/Fuzz/range-response.php',
            'strength-estimator' => 'tests/Fuzz/strength-estimator.php',
        ],
    ],
];
