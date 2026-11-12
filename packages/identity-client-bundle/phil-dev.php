<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true, 'words' => ['PKCE']],
    'phpstan' => [
        // The fuzz target runs inside nikic/php-fuzzer, the worker page inside FrankenPHP: each gives them what PHPStan
        // does not know.
        'excludePaths' => ['tests/Fuzz/*', 'tests/Worker/*'],
    ],
    'infection' => [
        'mutators' => [
            // The build of Symfony's bundle base class is empty: calling it or not changes nothing a test could see; the
            // call only keeps the override honest if the base class ever gains a step.
            'MethodCallRemoval' => [
                'ignoreSourceCodeByRegex' => [
                    '.*parent::build\\(\\$container\\);',
                ],
            ],
            // Equal to the nonce length, the ciphertext is empty and the decryption fails: `<=` and `<` give the same
            // answer, and the guard only keeps a shorter value from reaching the decryption.
            // The locks are released by hand, in a `finally`: asking for their release at destruction as well changes
            // nothing a test could see.
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    '.*createLock\\(.*, false\\);',
                ],
            ],
            'LessThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\\\\strlen\\(\\$sealed\\) <= .*',
                ],
            ],
        ],
    ],
    'fuzz' => [
        // The return_to policy on inputs nobody wrote: whatever it accepts stays on the application or a declared origin.
        'targets' => ['return-to' => 'tests/Fuzz/return-to.php'],
    ],
    // The page `composer bench:worker phil/identity-client-bundle` measures, served by FrankenPHP in worker mode.
    'bench' => [
        'worker' => ['script' => 'tests/Worker/worker.php', 'requests' => 1000, 'warmup' => 50],
    ],
];
