<?php

declare(strict_types=1);

use Phil\TenancyBundle\PhilTenancyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;

return [
    FrameworkBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    PhilTenancyBundle::class => ['all' => true],
];
