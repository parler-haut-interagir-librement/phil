<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\CommerceCatalogBundle\PhilCommerceCatalogBundle;
use Phil\CommercePromotionsBundle\PhilCommercePromotionsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilCommerceBundle::class => ['all' => true],
    PhilCommerceCatalogBundle::class => ['all' => true],
    PhilCommercePromotionsBundle::class => ['all' => true],
];
