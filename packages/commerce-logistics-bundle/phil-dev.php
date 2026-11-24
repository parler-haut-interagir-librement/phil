<?php

declare(strict_types=1);

use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    'phpstan' => [
        // The fuzz targets run inside nikic/php-fuzzer, which gives them `$config`; PHPStan knows neither.
        'excludePaths' => ['tests/Fuzz/*'],
        // La dette de phil/dev-tools v2 : ce que ses règles relèvent et qui reste à corriger.
        'ignoreErrors' => [
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
                    'src/Application/Port/CarrierGateway.php',
                    'src/Application/Port/PrintOnDemand.php',
                    'src/Domain/Repository/ConventionRepository.php',
                    'src/Domain/Repository/DeliveryMethodRepository.php',
                    'src/Domain/Repository/LocationRepository.php',
                    'src/Domain/Repository/PurchaseOrderRepository.php',
                    'src/Domain/Repository/SerialNumberRepository.php',
                    'src/Domain/Repository/ShipmentRepository.php',
                    'src/Domain/Repository/ShippingCostRepository.php',
                    'src/Domain/Repository/ShippingDocumentRepository.php',
                    'src/Domain/Repository/ShippingWaveRepository.php',
                    'src/Domain/Repository/StockPolicyRepository.php',
                    'src/Domain/Repository/StockRepository.php',
                ],
            ],
        ],
    ],
    // PHP's own sort direction, which the ORM takes; on PHP 8.5 it comes from symfony/polyfill-php86, in the global
    // namespace that the default layers do not cover.
    'deptrac' => ['allow' => ['^SortDirection$']],
    'infection' => [
        'mutators' => [
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    // The token and its expiry are set together and dropped together: one is never null without the
                    // other, so either of the first two tests stands for both.
                    '.*\\n\\+\\s*if \\(\\(null !== \\$this->token \\|\\| \\$this->tokenExpiresAt instanceof DateTimeImmutable\\) && \\$now < \\$this->tokenExpiresAt\\) \\{',
                    // The configuration reaches the extension processed: `carriers` always holds `canada_post`, which
                    // always holds `enabled`; the checks only type the array for PHPStan.
                    '.*\\n\\+\\s*\\$canadaPost = \\\\is_array\\(\\$config\\[.carriers.\\] \\?\\? null\\) \\|\\| \\\\is_array\\(.*',
                ],
            ],
            // The mutant swaps the operands of a comparison between two booleans for `a === b !== c`, which PHP does not
            // parse: it is no program a test could run, and Infection counts it as a syntax error.
            'NotIdentical' => [
                'ignoreSourceCodeByRegex' => ['if \\(\\(DeliveryKind::Pickup === \\$kind\\) !== \\(null !== \\$pickupLocation\\)\\) \\{'],
            ],
            // After `PurchaseOrder::receive()` accepted the SKU the order lists it, so `line()` is never null: the nullsafe
            // operators only tell PHPStan so.
            'NullSafePropertyCall' => [
                'ignoreSourceCodeByRegex' => ['.*\\$line\\??->(unitCost|owner).*'],
            ],
            // The quantities of a convention are keyed by convention then SKU: every server reads them in that key's
            // order, so the explicit ORDER BY, which the contract names, changes nothing a test could see.
            'ArrayItemRemoval' => [
                'ignoreSourceCodeByRegex' => ['.*findBy\\(\\[.convention. => \\$convention\\], \\[.sku. => .ASC.\\]\\).*'],
            ],
            // The newest receipt is picked in PHP among the rows, sorted: the limit only spares the server the others.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => ['.*->setMaxResults\\(1\\).*'],
            ],
            // SQLite returns the sums as integers; MySQL and PostgreSQL return them as strings, which the server suites
            // read: the casts are no difference a SQLite test could see.
            'CastInt' => [
                'ignoreSourceCodeByRegex' => ['.*\\$row\\[.(units|uncosted|value).\\].*'],
            ],
            // The rows of the sums already hold the keys the result names, and the kinds of a filter are bound as their
            // enumeration: on SQLite the unwrapped call returns what the wrapped one does.
            'UnwrapArrayMap' => [
                'ignoreSourceCodeByRegex' => ['.*(return \\$rows;|array_map\\(static fn \\(array \\$row\\): array|setParameter\\(.kinds., (array_map\\(static fn \\(CostEntryKind|\\$kinds\\))).*'],
            ],
            'FalseValue' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(true !== \\(\\$canadaPost\\[.enabled.\\] \\?\\? false\\)\\) \\{',
                ],
            ],
        ],
    ],
    'rector' => [
        'skip' => [
            // A tag placed before or after the service of a package the bundle does not depend on names it by a string:
            // `::class` would make the bundle depend on that package in the eyes of Deptrac and of the dependency
            // analysis, while the constraint is ignored when the package is not installed.
            StringClassNameToClassConstantRector::class => [
                'src/PhilCommerceLogisticsBundle.php',
                'tests/Unit/PhilCommerceLogisticsBundleTest.php',
            ],
        ],
    ],
    'fuzz' => [
        // The reader of Canada Post's answers on inputs nobody wrote, from the seeds of tests/Fuzz/corpus/<target>.
        'targets' => [
            'canada-post-answers' => 'tests/Fuzz/canada-post-answers.php',
        ],
    ],
];
