<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // The worker-mode page runs inside FrankenPHP, which gives it `frankenphp_handle_request()`, unknown to PHPStan.
        'excludePaths' => ['tests/Worker/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : complexité cognitive au-dessus de 25, à découper : PatternGenerator::generate() (38).
            [
                'identifier' => 'complexity.functionLike',
                'path' => 'tests/Generator/PatternGenerator.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'path' => 'src/Fixer/Fixer.php',
                'count' => 1,
            ],
        ],
    ],
    'deptrac' => [
        // The typographer walks HTML through the DOM extension of PHP, which is outside the default.
        'ruleset' => [
            'PhilTypography' => ['DefaultAllowed', 'Forbidden'],
        ],
    ],
    // The page `composer bench:worker phil/typography` measures, served by FrankenPHP in worker mode.
    'bench' => [
        'worker' => ['script' => 'tests/Worker/worker.php', 'requests' => 1000, 'warmup' => 50],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
