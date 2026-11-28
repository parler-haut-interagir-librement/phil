<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\WaitlistBundle\PhilWaitlistBundle;
use Phil\WorkshopsBundle\PhilWorkshopsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilWaitlistBundle::class => ['all' => true],
    PhilWorkshopsBundle::class => ['all' => true],
];
