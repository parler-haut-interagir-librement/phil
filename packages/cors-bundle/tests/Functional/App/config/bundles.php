<?php

declare(strict_types=1);

use Phil\CorsBundle\PhilCorsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilCorsBundle::class => ['all' => true],
];
