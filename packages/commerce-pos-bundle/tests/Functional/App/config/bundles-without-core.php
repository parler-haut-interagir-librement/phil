<?php

declare(strict_types=1);

use Phil\CommercePosBundle\PhilCommercePosBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilCommercePosBundle::class => ['all' => true],
];
