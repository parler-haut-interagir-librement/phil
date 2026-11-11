<?php

declare(strict_types=1);

use Phil\MonitoringBundle\PhilMonitoringBundle;
use Phil\WellKnownBundle\PhilWellKnownBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilWellKnownBundle::class => ['all' => true],
    PhilMonitoringBundle::class => ['all' => true],
];
