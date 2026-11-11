<?php

declare(strict_types=1);

use Phil\RouteAccessCheckBundle\PhilRouteAccessCheckBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;

return [
    FrameworkBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    PhilRouteAccessCheckBundle::class => ['all' => true],
];
