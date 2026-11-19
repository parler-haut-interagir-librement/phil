<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\ErrorTrackingBundle\PhilErrorTrackingBundle;
use Phil\MonitoringBundle\PhilMonitoringBundle;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilMonitoringBundle::class => ['all' => true],
    PhilNotificationsBundle::class => ['all' => true],
    PhilRateLimitBundle::class => ['all' => true],
    PhilErrorTrackingBundle::class => ['all' => true],
];
