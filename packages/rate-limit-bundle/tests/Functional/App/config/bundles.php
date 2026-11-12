<?php

declare(strict_types=1);

use Phil\RateLimitBundle\PhilRateLimitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilRateLimitBundle::class => ['all' => true],
];
