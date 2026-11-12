<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\ApiContractBundle\PhilApiContractBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilApiContractBundle::class => ['all' => true],
];
