<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\CalendarBundle\PhilCalendarBundle;
use Phil\RecurrenceBundle\PhilRecurrenceBundle;
use Phil\ReservationBundle\PhilReservationBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    PhilRecurrenceBundle::class => ['all' => true],
    PhilCalendarBundle::class => ['all' => true],
    PhilReservationBundle::class => ['all' => true],
];
