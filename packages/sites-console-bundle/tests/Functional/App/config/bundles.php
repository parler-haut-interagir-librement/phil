<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\MonitoringBundle\PhilMonitoringBundle;
use Phil\SitesConsoleBundle\PhilSitesConsoleBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

return [
    FrameworkBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilMonitoringBundle::class => ['all' => true],
    PhilSitesConsoleBundle::class => ['all' => true],
];
