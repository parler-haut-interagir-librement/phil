<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : API interne de Doctrine, PHPUnit à remplacer par une API publique.
            [
                'identifier' => 'method.internal',
                'paths' => [
                    'src/Infrastructure/Doctrine/DatabaseClients.php',
                    'tests/Unit/PhilBackupBundleTest.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // A tool's running time is read from the monotonic clock between two checks at least 0.2 s apart: it never
            // lands on the limit itself, so stopping or warning at the limit or just past it cannot be told apart.
            'GreaterThanOrEqualTo' => [
                'ignoreSourceCodeByRegex' => [
                    '.*\$elapsed >= \$timeout.*',
                    '.*\$elapsed >= \$warnAfter.*',
                ],
            ],
        ],
    ],
    // The package is released from its own repository: its CHANGELOG uses every section.
    'docs' => ['public' => true],
];
