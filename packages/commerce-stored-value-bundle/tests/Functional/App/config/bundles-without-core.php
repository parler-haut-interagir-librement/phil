<?php

declare(strict_types=1);

use Phil\CommerceStoredValueBundle\PhilCommerceStoredValueBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilCommerceStoredValueBundle::class => ['all' => true],
];
