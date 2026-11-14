<?php

declare(strict_types=1);

use Phil\SettingsBundle\PhilSettingsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilSettingsBundle::class => ['all' => true],
];
