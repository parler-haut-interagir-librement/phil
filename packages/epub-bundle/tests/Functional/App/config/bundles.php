<?php

declare(strict_types=1);

use Phil\EpubBundle\PhilEpubBundle;
use Phil\FontsBundle\PhilFontsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilFontsBundle::class => ['all' => true],
    PhilEpubBundle::class => ['all' => true],
];
