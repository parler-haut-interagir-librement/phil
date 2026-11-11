<?php

declare(strict_types=1);

use Phil\MenuBundle\PhilMenuBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilMenuBundle::class => ['all' => true],
];
