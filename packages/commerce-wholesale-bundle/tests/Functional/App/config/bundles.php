<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\CommerceWholesaleBundle\PhilCommerceWholesaleBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilCommerceBundle::class => ['all' => true],
    PhilCommerceWholesaleBundle::class => ['all' => true],
];
