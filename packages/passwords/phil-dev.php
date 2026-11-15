<?php

declare(strict_types=1);

use Phil\Passwords\Generator\Internal\UniqueDecoding;
use Phil\Passwords\Generator\PassphraseGenerator;

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
                    // The completions of the Sardinas–Patterson test are looked up by a dangling suffix, never empty
                    // nor a whole codeword: the test ends on either first, so neither prefix is ever read.
                    'for \\(\\$length = 1; \\$length < \\\\strlen\\(\\$codeword\\); \\+\\+\\$length\\) \\{',
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
                    'for \\(\\$length = 1; \\$length < \\\\strlen\\(\\$codeword\\); \\+\\+\\$length\\) \\{',
                ],
            ],
            // The two shortcuts of PassphraseGenerator::separates() only spare the Sardinas–Patterson test where its
            // answer is known: a separator that no word holds closes each word, and a digit that no word holds marks
            // the end of the word that takes it, a digit of the separator included. Taking the test instead gives the
            // same answer, more slowly.
            'LogicalNot' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\'\' !== \\$separator && !\\$digitCodewords && false === strpbrk\\(\\$written, \\$separator\\)\\) \\{',
                ],
            ],
            'LogicalAndSingleSubExprNegation' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\'\' !== \\$separator && !\\$digitCodewords && false === strpbrk\\(\\$written, \\$separator\\)\\) \\{',
                ],
            ],
            'Concat' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$digitCodewords = \\$policy->includeDigit && false !== strpbrk\\(\\$written\\.\\$separator, .*',
                ],
            ],
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$digitCodewords = \\$policy->includeDigit && false !== strpbrk\\(\\$written\\.\\$separator, .*',
                ],
            ],
            // A date is at most ten characters, a four-digit year, a month, a day and two separators: a longer part is
            // read and refused.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'for \\(\\$j = \\$i; \\$j < min\\(\\$count, \\$i \\+ 10\\); \\+\\+\\$j\\) \\{',
                ],
            ],
            // The sets of codewords, of dangling suffixes seen and of forms are read through `isset()` and `array_keys()`
            // only: a value of `false` is as present as `true`, so the value stored with a key means nothing.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$(set|seen|forms)\\[.*\\] = true;',
                ],
            ],
            // The two shortcuts that return `true` before the Sardinas–Patterson walk — a prefix code, or a separator that
            // closes every word — spare its work: without them the walk starts from nothing to follow and answers `true`.
            'ReturnRemoval' => [
                'ignore' => [
                    UniqueDecoding::class.'::holds::49',
                    PassphraseGenerator::class.'::separates::183',
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
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
