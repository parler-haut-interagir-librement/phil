<?php

declare(strict_types=1);

use Phil\NotificationsBundle\PhilNotificationsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilNotificationsBundle::class => ['all' => true],
];
