<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        // JMAP, the protocol of RFC 8620, whose JMAP Contacts (RFC 9610) exchanges JSContact cards.
        'words' => ['JMAP'],
    ],
    'phpstan' => [
        // The fuzz target runs under nikic/php-fuzzer, which only vendor-bin/fuzzer installs.
        'excludePaths' => ['tests/Fuzz/*'],
    ],
    'fuzz' => [
        // The JSContact reader on texts nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'jscontact-reader' => 'tests/Fuzz/jscontact-reader.php',
        ],
    ],
    'infection' => [
        'mutators' => [
            // Read only once a street part is on a line, and the first street part of an address sets it.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['\\$named = false;'],
            ],
            // Two components of a name never stand at the same position.
            'LessThan' => [
                'ignoreSourceCodeByRegex' => ['.*\\$positions\\[\'surname\'\\] < \\$positions\\[\'given\'\\].*'],
            ],
        ],
    ],
];
