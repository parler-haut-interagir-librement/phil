<?php

declare(strict_types=1);

use Phil\CommerceReportsBundle\PhilCommerceReportsBundle;
use Phil\ReportingBundle\PhilReportingBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilReportingBundle::class => ['all' => true],
    PhilCommerceReportsBundle::class => ['all' => true],
];
