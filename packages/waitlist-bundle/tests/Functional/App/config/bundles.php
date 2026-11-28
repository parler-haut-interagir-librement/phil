<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use Phil\WaitlistBundle\PhilWaitlistBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilNotificationsBundle::class => ['all' => true],
    PhilWaitlistBundle::class => ['all' => true],
];
