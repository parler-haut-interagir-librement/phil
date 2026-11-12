<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\BrowserSecurityBundle\PhilBrowserSecurityBundle;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Phil\StructuredDataBundle\PhilStructuredDataBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilRateLimitBundle::class => ['all' => true],
    PhilStructuredDataBundle::class => ['all' => true],
    PhilBrowserSecurityBundle::class => ['all' => true],
];
