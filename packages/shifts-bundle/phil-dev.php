<?php

declare(strict_types=1);

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests', 'config'],
    'deptrac' => [
        // The repositories, the tables and the transaction read and write through Doctrine DBAL; the Messenger handler
        // logs what it could not allocate.
        'allow' => ['^Doctrine\\\\DBAL\\\\.*', '^Psr\\\\Log\\\\LoggerInterface$', '^Psr\\\\Log\\\\NullLogger$'],
    ],
    'twig' => ['paths' => ['templates']],
    'docs' => ['public' => true, 'translations' => 'blocking'],
    'infection' => [
        'mutators' => [
            // The file argument of the command is required, so always a text: the check of its type is for PHPStan. The
            // include path searches the same relative path as the check of the file did, and a read longer than the limit
            // by one byte or by two is refused alike.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    '.*is_string\\(\\$file\\) && is_file\\(\\$file\\).*',
                    // A pending offer always names who took it: the second operand is there for PHPStan.
                    '.*null !== \\$offer->takenBy && SwapStatus::Pending === \\$offer->status && .*',
                ],
            ],
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => ['.*@file_get_contents\\(\\$file, false, null, 0, ShiftPlan::MAX_BYTES \\+ 1\\).*'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*@file_get_contents\\(\\$file, false, null, 0, ShiftPlan::MAX_BYTES \\+ 1\\).*'],
            ],
            // The revision is an integer column: bound as text, it compares and is stored the same.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*\\[\'revision\' => Types::INTEGER\\],.*'],
            ],
        ],
    ],
];
