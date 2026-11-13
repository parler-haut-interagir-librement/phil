<?php

declare(strict_types=1);

use Phil\FontsBundle\PhilFontsBundle;
use Phil\PrintPreflightBundle\PhilPrintPreflightBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilFontsBundle::class => ['all' => true],
    PhilPrintPreflightBundle::class => ['all' => true],
];
