<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'fuzz' => [
        // The readers of what nobody controls, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            // The text of an address, as a form posts it.
            'email-address' => 'tests/Fuzz/email-address.php',
            // The IANA list of top-level domains, as the importer reads it.
            'top-level-domains' => 'tests/Fuzz/top-level-domains.php',
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        'ignoreErrors' => [
            // The constraint sits beside its validator, as Symfony pairs a Constraint and its ConstraintValidator: an
            // Attribute namespace would split the pair.
            [
                'identifier' => 'symplify.requireAttributeNamespace',
                'path' => 'src/Validator/DeliverableEmailAddress.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // `VERSION` is read only once `is_file()` found it: `file_get_contents()` returns its text, and the cast only
            // types the `false` of a read that cannot fail there.
            'CastString' => [
                'ignoreSourceCodeByRegex' => ['\\$current = is_file\\(.*\\(string\\) file_get_contents\\(.*'],
            ],
            // The two early exits of the distance stop the work once it can only exceed the limit: without them the
            // band is filled to its end and the final cap gives the same `$limit + 1`; only the cost changes.
            'ReturnRemoval' => [
                'ignoreSourceCodeByRegex' => ['return \\$over;'],
            ],
            // The Levenshtein filter only sets aside a pair the band would also put over the limit: a looser filter
            // (three times the limit) leaves the band to find the same `$limit + 1`. The rows hold one cell per
            // character of the second string and the column before it, each written before it is read: a longer row
            // has a cell nobody reads, a row filled from 1 a first cell that the first row and `min($i, $over)` write
            // anyway.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(.*levenshtein\\(\\$a, \\$b\\) > 2 \\* \\$limit\\) \\{',
                    '\\$row = array_fill\\(0, \\$lengthB \\+ 1, \\$over\\);',
                ],
            ],
            // The filter applied only with the length check: the band then finds the same `$limit + 1` by itself.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['if \\(.*levenshtein\\(\\$a, \\$b\\) > 2 \\* \\$limit\\) \\{'],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
