<?php

declare(strict_types=1);

use Phil\CommercePromotionsBundle\Application\PromotionEvaluator;
use Phil\CommercePromotionsBundle\Application\PromotionOrder;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;

/*
 * What the shared development tooling reads of this package: its paths and its exceptions to the shared
 * configurations, nothing else.
 */
return [
    'paths' => ['src', 'tests'],
    'infection' => [
        'mutators' => [
            // The sorter takes the codes as they are: a numeric code is an integer key of the map and goes through it as
            // an integer, then comes back as the key of the same promotion; the cast only serves the list<string> of the
            // sorter's signature.
            'UnwrapArrayMap' => [
                'ignore' => [PromotionOrder::class.'::sort'],
            ],
            // The lines kept for a bundle come back numbered from zero for the list<OrderLine> the next steps are typed
            // with; the discounts are read line by line under the keys the lines keep, so a gap changes nothing.
            'UnwrapArrayValues' => [
                'ignore' => [PromotionEvaluator::class.'::discounts'],
            ],
        ],
    ],
    'phpstan' => [
        'ignoreErrors' => [
            // La méthode dépréciée de BundleInterface que lisent aussi les autres modules du commerce ; à remplacer avec eux.
            [
                'identifier' => 'method.deprecatedInterface',
                'path' => 'tests/Functional/BundleRegistrationTest.php',
                'count' => 1,
            ],
        ],
    ],
    'rector' => [
        'skip' => [
            // A tag placed before or after the service of a package the bundle does not depend on names it by a string:
            // `::class` would make the bundle depend on that package in the eyes of Deptrac and of the dependency
            // analysis, while the constraint is ignored when the package is not installed.
            StringClassNameToClassConstantRector::class => [
                'src/Application/FreeShippingProcessor.php',
            ],
        ],
    ],
    'docs' => ['public' => true, 'translations' => 'blocking'],
];
