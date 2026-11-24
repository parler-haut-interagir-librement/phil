<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\FollowBundle\PhilFollowBundle;
use Phil\NotificationsBundle\PhilNotificationsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilNotificationsBundle::class => ['all' => true],
    PhilFollowBundle::class => ['all' => true],
];
