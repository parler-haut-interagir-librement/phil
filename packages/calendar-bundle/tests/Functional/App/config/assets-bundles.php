<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\CalendarBundle\PhilCalendarBundle;
use Phil\RecurrenceBundle\PhilRecurrenceBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\AssetMapper\AssetMapperBundle;

return [
    FrameworkBundle::class => ['all' => true],
    AssetMapperBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilRecurrenceBundle::class => ['all' => true],
    PhilCalendarBundle::class => ['all' => true],
];
