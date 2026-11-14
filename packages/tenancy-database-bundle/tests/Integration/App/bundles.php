<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\TenancyBundle\PhilTenancyBundle;
use Phil\TenancyDatabaseBundle\PhilTenancyDatabaseBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilTenancyBundle::class => ['all' => true],
    PhilTenancyDatabaseBundle::class => ['all' => true],
];
