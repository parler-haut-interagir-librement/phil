<?php

declare(strict_types=1);

use Phil\MailAuthCheck\Internal\SpfWalk;

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        'translations' => 'blocking',
        // The names of the records the package checks, and the file that configures the system's resolver.
        'words' => ['BIMI', 'DMARC', 'resolv'],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The readers of SPF, DKIM and DMARC records and the SPF walk, from the seeds of
        // tests/Fuzz/corpus/mail-auth-records.
        'targets' => [
            'mail-auth-records' => 'tests/Fuzz/records.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // Every line below changes nothing a caller or a test can see: the reason is given above each.
            //
            // A discovery holds a record exactly when it holds the domain it was found at: the second test only tells
            // PHPStan so.
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => ['.*if \(!\$record instanceof DmarcRecord \|\| null === \$discovery->foundAt\) \{.*'],
            ],
            // `preg_replace()` gives null only for a pattern that is wrong, which this one is not; a walk gives a reason
            // whenever it gives no record: the casts only tell PHPStan the values are text.
            'CastString' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\$encoded = \(string\) preg_replace\(.*',
                    '.*\$this->broken\[\$target\] = \(string\) \$reason;',
                ],
            ],
            // Once `spend()` has said false, it says false again without counting: going on to the next mechanism counts
            // and follows nothing more than leaving the loop.
            'Break_' => [
                'ignore' => [SpfWalk::class.'::walk'],
            ],
            // `isset()` is true of a key whose value is false: the domains being walked are told by their key alone.
            'TrueValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*return \$this->walk\(\$record, \$stack \+ \[\$target => true\]\);',
                    '.*\$all = \$walk->walk\(\$record, \[\$domain => true\]\);',
                ],
            ],
            // The checks are only run in order: their keys are never read, and `array_values()` tells PHPStan they are a
            // list.
            'UnwrapArrayValues' => [
                'ignoreSourceCodeByRegex' => ['.*\$this->checks = null === \$checks \? self::defaultChecks\(\) : array_values\(.*'],
            ],
            // A record that `isSpf()` accepted has a space or nothing after `v=spf1`, and the terms are split on spaces:
            // reading from the space or after it gives the same terms.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*\$terms = preg_split\(\'/ \+/\', substr\(\$text, 6\), .*'],
            ],
            // Without `D`, `$` also matches before a final line break. The name of a tag has lost its trailing white
            // space, line breaks included, and the value of a mechanism or a modifier was read by a pattern that refuses
            // any line break: none of these texts can end with one.
            'PregMatchRemoveFlags' => [
                'ignoreSourceCodeByRegex' => [
                    '.*preg_match\(\'/\^\[A-Za-z\]\[A-Za-z0-9_\]\*\$/D\', \$name\).*',
                    '.*preg_match\(\'.\^\(\?::\(\[\^/\]\+\)\)\?.*',
                    '.*preg_match\(\'.\^:\(\[\^/\]\+\)\(\?:/.*',
                    '.*preg_match\(\'/\^\[\\\\x21-\\\\x7E\]\{1,253\}\$/D\', \$value\).*',
                ],
            ],
        ],
    ],
];
