<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => [
        'public' => true,
        // What the core's modules sell.
        'words' => ['sellables'],
    ],
    'infection' => [
        'mutators' => [
            // `stream_get_contents()` gives `false` only on a stream already closed, which the request never hands over: the
            // cast changes nothing that can be observed.
            'CastString' => ['ignoreSourceCodeByRegex' => ['.*\\(string\\) stream_get_contents\\(.*']],
        ],
    ],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
            // à corriger : type d’argument trop large relevé par bleedingEdge.
            [
                'identifier' => 'argument.type',
                'path' => 'src/Domain/Model/OutgoingMessage.php',
                'count' => 1,
            ],
            // à corriger : API dépréciée de Symfony à remplacer.
            [
                'identifier' => 'method.deprecatedInterface',
                'path' => 'tests/Functional/BundleRegistrationTest.php',
                'count' => 1,
            ],
            // à corriger : déplacer les interfaces dans un espace de noms Contract (rupture de l’API publique).
            [
                'identifier' => 'symplify.requiredInterfaceContractNamespace',
                'paths' => [
                    'src/Application/Port/ForwardedOrderHandler.php',
                    'src/Application/Port/MessageTransport.php',
                    'src/Application/Port/PartnerReturnHandler.php',
                    'src/Application/Port/PartnerShipmentHandler.php',
                    'src/Domain/Repository/LedgerRepository.php',
                    'src/Domain/Repository/MessageRepository.php',
                    'src/Domain/Repository/PartnerRepository.php',
                    'src/Domain/Repository/PartnerReturnRepository.php',
                    'src/Domain/Repository/PartnerShipmentRepository.php',
                    'src/Domain/Repository/PublishedPriceRepository.php',
                    'src/Domain/Repository/SyndicatedProductRepository.php',
                ],
            ],
        ],
    ],
    // PHP's own sort direction, which the ORM takes; on PHP 8.5 it comes from symfony/polyfill-php86, in the global
    // namespace that the default layers do not cover.
    'deptrac' => ['allow' => ['^SortDirection$']],
    'fuzz' => [
        // The reader of a partner's parcels and returns on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'partner-messages' => 'tests/Fuzz/partner-messages.php',
        ],
    ],
];
