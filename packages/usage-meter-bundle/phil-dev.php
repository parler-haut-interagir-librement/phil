<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : type d’argument trop large relevé par bleedingEdge.
            [
                'identifier' => 'argument.type',
                'path' => 'src/Infrastructure/Configuration/ConfiguredMeters.php',
                'count' => 1,
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            'DecrementInteger' => [
                // A quota is exceeded only on a meter with a limit, whose remaining() is never null: the fallback
                // to zero of a refusal cannot be reached, so its mutants cannot be told apart.
                'ignoreSourceCodeByRegex' => ['return Decision::refused\\(\\$quotaExceeded->usage->limit->value, \\$quotaExceeded->usage->remaining\\(\\) \\?\\? 0, .*'],
            ],
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['return Decision::refused\\(\\$quotaExceeded->usage->limit->value, \\$quotaExceeded->usage->remaining\\(\\) \\?\\? 0, .*'],
            ],
            'Coalesce' => [
                'ignoreSourceCodeByRegex' => ['return Decision::refused\\(\\$quotaExceeded->usage->limit->value, \\$quotaExceeded->usage->remaining\\(\\) \\?\\? 0, .*'],
            ],
        ],
    ],
];
