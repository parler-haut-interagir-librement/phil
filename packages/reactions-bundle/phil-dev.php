<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true, 'words' => ['chromedriver']],
    'phpstan' => [
        // The worker page runs inside FrankenPHP, which gives it what PHPStan does not know.
        'excludePaths' => ['tests/Worker/*'],
        'ignoreErrors' => [
            // The repository stays in Domain\Repository, as Arkitect requires of the ports of a domain's models.
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'path' => 'src/Domain/Repository/ReactionRepository.php',
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // SQLite gives the count as a number; other drivers may give a numeric string, which the cast turns into one.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$counts\\[\\$row\\[\'content\'\\]\\]\\[\\$row\\[\'code\'\\]\\] = \\(int\\) \\$row\\[\'total\'\\];',
                ],
            ],
        ],
    ],
    // The page `composer bench:worker phil/reactions-bundle` measures, served by FrankenPHP in worker mode.
    'bench' => [
        'worker' => ['script' => 'tests/Worker/worker.php', 'requests' => 1000, 'warmup' => 50],
    ],
];
