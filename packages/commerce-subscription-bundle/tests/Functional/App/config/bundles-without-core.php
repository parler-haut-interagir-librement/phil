<?php

declare(strict_types=1);

use Phil\CommerceSubscriptionBundle\PhilCommerceSubscriptionBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilCommerceSubscriptionBundle::class => ['all' => true],
];
