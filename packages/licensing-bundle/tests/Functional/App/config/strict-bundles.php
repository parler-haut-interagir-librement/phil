<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\LicensingBundle\PhilLicensingBundle;
use Phil\PagedMediaBundle\PhilPagedMediaBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilPagedMediaBundle::class => ['all' => true],
    PhilLicensingBundle::class => ['all' => true],
];
