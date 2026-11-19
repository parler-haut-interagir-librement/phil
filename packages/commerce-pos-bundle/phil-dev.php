<?php

declare(strict_types=1);

/*
 * What phil/dev-tools reads of this package: its paths and its exceptions to the shared configurations.
 */
return [
    'paths' => ['src', 'tests'],
    'docs' => ['public' => true],
    // PHP's own sort direction, which the ORM takes; on PHP 8.5 it comes from symfony/polyfill-php86, in the global
    // namespace that the default layers do not cover.
    'deptrac' => ['allow' => ['^SortDirection$']],
    'phpstan' => [
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
                    'src/Domain/Repository/CashSessionRepository.php',
                    'src/Domain/Repository/RegisterRepository.php',
                    'src/Domain/Repository/RegisterSaleRepository.php',
                ],
            ],
        ],
    ],
    'infection' => [
        'mutators' => [
            // The quantity of the pricing context a preset hands to the core's `changeAudience()`: the core prices each
            // line again at the line's own quantity, keeping only the moment, the group and the channel of the context.
            'IncrementInteger' => [
                'ignoreSourceCodeByRegex' => [
                    '\\$this->modifier->changeAudience\\(\\$order, \\$channel, \\$chosen->customerGroup\\(\\), PricingContext::of\\(1, .*',
                ],
            ],
            // The drawer of an option value is set exactly when the value is one to draw, described by the core: a form
            // is looked up only for a drawer, and a drawer only comes from a value. The tests on the value and the
            // drawer serve the types of what follows.
            'LogicalAnd' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(\\$value instanceof OptionValue && null !== \\$drawer && \\$form instanceof TillDrawForm\\) \\{',
                ],
            ],
            'LogicalOr' => [
                'ignoreSourceCodeByRegex' => [
                    'if \\(!\\$value instanceof OptionValue \\|\\| null === \\$drawer \\|\\| !\\$value->isPending\\(\\)\\) \\{',
                    // Both symfony/password-hasher and symfony/rate-limiter are installed where the tests run: the
                    // cashier switch refuses when either is missing, and no test can uninstall one of them.
                    'if \\(!class_exists\\(NativePasswordHasher::class\\) \\|\\| !interface_exists\\(RateLimiterFactoryInterface::class\\)\\) \\{',
                ],
            ],
        ],
    ],
];
