<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\EditLockBundle\PhilEditLockBundle;
use Phil\UjoBundle\PhilUjoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MercureBundle\MercureBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    MercureBundle::class => ['all' => true],
    PhilUjoBundle::class => ['all' => true],
    PhilEditLockBundle::class => ['all' => true],
];
