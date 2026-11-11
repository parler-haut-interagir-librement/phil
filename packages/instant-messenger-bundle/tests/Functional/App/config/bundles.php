<?php

declare(strict_types=1);

use Phil\InstantMessengerBundle\PhilInstantMessengerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilInstantMessengerBundle::class => ['all' => true],
];
