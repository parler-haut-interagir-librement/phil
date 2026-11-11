<?php

declare(strict_types=1);

use Phil\ConsoleBundle\PhilConsoleBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;

return [
    FrameworkBundle::class => ['all' => true],
    MonologBundle::class => ['all' => true],
    PhilConsoleBundle::class => ['all' => true],
];
