<?php

declare(strict_types=1);

use Phil\OpenBadgesBundle\PhilOpenBadgesBundle;
use Phil\RateLimitBundle\PhilRateLimitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilOpenBadgesBundle::class => ['all' => true],
    PhilRateLimitBundle::class => ['limited' => true],
];
