<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        'translations' => 'blocking',
        // The title of a book quoted as an example, the French quotation marks, and the references never cited.
        'words' => ['TeXbook', 'guillemets', 'uncited'],
    ],
    'infection' => [
        'mutators' => [
            // The items of a decoded list are a list, which `array_values` only tells the type system.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['return array_values\\(array_map\\(\\$this->item\\(\\.\\.\\.\\), \\$items\\)\\);'],
            ],
            // `isset` is true for false as well as for true.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$seen\\[\\$reference->id\\] = true;',
                    '\\$ids\\[\\$id\\] = true;',
                ],
            ],
            // The collator answers an integer for two valid strings, and the PHP cast only serves the signature.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['\\(int\\) \\$collator->compare\\(.*'],
            ],
            // Casts of a value that is a string whenever the code goes on: the input was checked as UTF-8, the
            // transliterator and the normaliser answer false only for what they cannot read, and a null concatenates
            // as the empty string.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$line = 0 === \\$index \\? \\(string\\) preg_replace\\(.*',
                    '\\$family = \\$author instanceof Name \\? \\(string\\) Transliterator.*',
                    '\\$family = \\(string\\) preg_replace\\(\'/\\[\\^a-z0-9\\]/\'.*',
                    '\\(string\\) \\$reference->date\\(DateVariable::Issued\\)\\?->startYear\\(\\),',
                    '\\$decomposed = \\(string\\) Normalizer::normalize\\(mb_strtolower.*',
                    '\\$given = \\$initials \\? \\$name->initials\\(\\) : \\(string\\) \\$name->given;',
                    '\\$given = trim\\(\\(\\$initials \\? \\$name->initials\\(\\) : \\(string\\) \\$name->given\\).*',
                    '.*\\(1 === \\$date->day \\? \'1er\' : \\(string\\) \\$date->day\\).*',
                    '.*array_filter\\(\\[\\(string\\) \\$number, \\$locator\\].*',
                ],
            ],
            // The transliterator exists whenever the intl extension does, which the package requires.
            'NullSafeMethodCall' => [
                'ignoreSourceCodeByRegex' => ['.*Transliterator::create\\(\'Any-Latin; Latin-ASCII; Lower\\(\\)\'\\)\\?->transliterate.*'],
            ],
            // A record is open exactly when its tag is known.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(null !== \\$record && null !== \\$tag && \'\' !== trim\\(\\$line\\)\\) \\{',
                    // A month outside 1 to 12 is refused by the date after it, which the reader then catches.
                    'return \\$number >= 1 && \\$number <= 12 \\? \\$number : null;',
                ],
            ],
            'LogicalOr' => [
                // The first and the last lowercase word are set together.
                'ignoreSourceCodeByRegex' => ['if \\(null === \\$firstLower \\|\\| null === \\$lastLower\\) \\{'],
            ],
            // A letter that is not lowercase is not its own lowercase either, so `strtolower` finds the same.
            'MBString' => [
                'ignoreSourceCodeByRegex' => ['&& mb_strtolower\\(\\$letter\\) === \\$letter'],
            ],
            // The characters of a set, in any order.
            'Concat' => [
                'ignoreSourceCodeByRegex' => [
                    '.*strcspn\\(\\$this->input, ",\\\\t\\\\n\\\\r "\\.\\$close.*',
                    '\\$where = \'\' !== \\$place && \'\' !== \\$publisher \\? \\$place\\.\\$terms->colon\\.\\$publisher : \\$place\\.\\$publisher;',
                    'return \'\' !== \\$place && \'\' !== \\$publisher \\? \\$place\\.\\$terms->colon\\.\\$publisher : \\$place\\.\\$publisher;',
                    // The prefixes tell kinds of keys apart that a folded title, a year and a name cannot make alike.
                    'return \'work:\'\\.implode\\(.*',
                ],
            ],
            'ConcatOperandRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$where = \'\' !== \\$place && \'\' !== \\$publisher \\? \\$place\\.\\$terms->colon\\.\\$publisher : \\$place\\.\\$publisher;',
                    'return \'\' !== \\$place && \'\' !== \\$publisher \\? \\$place\\.\\$terms->colon\\.\\$publisher : \\$place\\.\\$publisher;',
                    'return \'work:\'\\.implode\\(.*',
                ],
            ],
            // The patterns match text that was trimmed, or cut at its line, or never ends with a new line.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    '.*preg_match\\(\'#\\^\\(-\\?\\\\d\\{1,4\\}\\).*',
                    '.*preg_match\\(\'/\\^\\(-\\?\\\\d\\{1,4\\}\\)\\(\\?:-.*',
                    '.*preg_match\\(\'/\\^\\(\\[A-Z\\]\\[A-Z0-9\\]\\)  -.*',
                ],
            ],
            'PregMatchRemoveDollar' => [
                'ignoreSourceCodeByRegex' => ['.*preg_match\\(\'/\\^\\(\\[A-Z\\]\\[A-Z0-9\\]\\)  -.*'],
            ],
            // The whole match of a date starts with its year, which the cast reads alone.
            'DecrementInteger' => [
                'ignoreSourceCodeByRegex' => ['return new PartialDate\\(\\(int\\) \\$matches\\[1\\].*'],
            ],
            // A fifth byte of an accent's letter is never part of the first character.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['\\$base = mb_substr\\(substr\\(\\$latex, \\$start, 4\\), 0, 1\\);'],
            ],
            // A brace after an unbraced accent is dropped by the loop that reads the text, as one the accent skips.
            'LogicalAndSingleSubExprNegation' => [
                'ignoreSourceCodeByRegex' => ['if \\(\\$braced && \\$after < \\$length && \'}\' === \\$latex\\[\\$after\\]\\) \\{'],
            ],
            // Year 0 is refused before, and a period is read only after a line end.
            'GreaterThan' => [
                'ignoreSourceCodeByRegex' => ['.*checkdate\\(\\$month, \\$day, \\$year > 0 \\? \\$year : 2000\\).*'],
            ],
            // Year 0 does not exist.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['return \\$year < 0 \\? abs\\(\\$year\\).*'],
            ],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The readers on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'bibtex' => 'tests/Fuzz/bibtex.php',
            'ris' => 'tests/Fuzz/ris.php',
            'csl-json' => 'tests/Fuzz/csl-json.php',
        ],
    ],
];
