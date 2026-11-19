<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Phil\UsageMeterBundle\PhilUsageMeterBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilRateLimitBundle::class => ['all' => true],
    PhilUsageMeterBundle::class => ['all' => true],
];
