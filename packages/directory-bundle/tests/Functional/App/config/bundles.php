<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\AddressBundle\PhilAddressBundle;
use Phil\ContactBundle\PhilContactBundle;
use Phil\ContentBundle\PhilContentBundle;
use Phil\DirectoryBundle\PhilDirectoryBundle;
use Phil\GeoBundle\PhilGeoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilContentBundle::class => ['all' => true],
    PhilContactBundle::class => ['all' => true],
    PhilAddressBundle::class => ['all' => true],
    PhilGeoBundle::class => ['all' => true],
    PhilDirectoryBundle::class => ['all' => true],
];
