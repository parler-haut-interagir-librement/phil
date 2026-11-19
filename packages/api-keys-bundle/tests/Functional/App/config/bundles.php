<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\ApiContractBundle\PhilApiContractBundle;
use Phil\ApiKeysBundle\PhilApiKeysBundle;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Phil\UsageMeterBundle\PhilUsageMeterBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilApiContractBundle::class => ['all' => true],
    PhilRateLimitBundle::class => ['all' => true],
    PhilUsageMeterBundle::class => ['all' => true],
    PhilApiKeysBundle::class => ['all' => true],
];
