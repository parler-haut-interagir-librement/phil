<?php

declare(strict_types=1);

use Phil\PwaBundle\PhilPwaBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilPwaBundle::class => ['all' => true],
];
