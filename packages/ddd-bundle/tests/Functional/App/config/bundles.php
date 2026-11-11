<?php

declare(strict_types=1);

use Phil\DddBundle\PhilDddBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilDddBundle::class => ['all' => true],
];
