<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'infection' => [
        'mutators' => [
            // A column of identifiers read from the acceptance table holds strings only, already in a list: keeping the
            // strings and renumbering change nothing that can be observed; they only serve PHPStan's list<string>.
            'UnwrapArrayFilter' => [
                'ignoreSourceCodeByRegex' => [
                    'return array_values\(array_filter\(\$this->connection->fetchFirstColumn\(\$sql, \$parameters, \$types\), is_string\(\.\.\.\)\)\);',
                    'foreach \(array_filter\(\$page, is_string\(\.\.\.\)\) as \$member\) \{',
                ],
            ],
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => [
                    'return array_values\(array_filter\(\$this->connection->fetchFirstColumn\(\$sql, \$parameters, \$types\), is_string\(\.\.\.\)\)\);',
                ],
            ],
            // DBAL gives the rows a delete removed as an integer on every driver the tests run; the cast only serves
            // the int|numeric-string of its signature.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    'return \(int\) \$this->connection->delete\(TermsTables::ACCEPTANCES, \[.member. => \$member\]\);',
                ],
            ],
            // A valid form holds the version it showed as the text of its hidden field: the check of the extension
            // makes any other value invalid, and an invalid form is refused before the field is read.
            'Throw_' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\\\is_string\(\$version\) \? \(int\) \$version : throw InvalidTerms::because\(.form_not_valid., .The form carries no version\..\).*',
                ],
            ],
        ],
    ],
];
