<?php

declare(strict_types=1);

use Rector\Symfony\CodeQuality\Rector\Class_\EventSubscriberToAttributeRector;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'phpstan' => [
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'path' => 'src/Application/Recipients.php',
                'count' => 1,
            ],
        ],
    ],
    'rector' => [
        'skip' => [
            // The test kernel registers the mail recorder with the kernel.event_subscriber tag and no autoconfiguration:
            // as a listener by attribute, it would no longer be registered at all.
            EventSubscriberToAttributeRector::class => ['tests/Functional/App/SentMessages.php'],
        ],
    ],
];
