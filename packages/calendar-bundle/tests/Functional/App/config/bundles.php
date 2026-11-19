<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\CalendarBundle\PhilCalendarBundle;
use Phil\RecurrenceBundle\PhilRecurrenceBundle;
use Phil\TaxonomyBundle\PhilTaxonomyBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\LiveComponent\LiveComponentBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    StimulusBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    LiveComponentBundle::class => ['all' => true],
    PhilRecurrenceBundle::class => ['all' => true],
    PhilTaxonomyBundle::class => ['all' => true],
    PhilCalendarBundle::class => ['all' => true],
];
