<?php

declare(strict_types=1);

use Phil\MapBundle\PhilMapBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\Map\UXMapBundle;
use Symfony\UX\StimulusBundle\StimulusBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    StimulusBundle::class => ['all' => true],
    UXMapBundle::class => ['all' => true],
    PhilMapBundle::class => ['all' => true],
];
