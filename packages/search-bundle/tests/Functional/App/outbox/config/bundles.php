<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\OutboxBundle\PhilOutboxBundle;
use Phil\SearchBundle\PhilSearchBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilOutboxBundle::class => ['all' => true],
    PhilSearchBundle::class => ['all' => true],
];
