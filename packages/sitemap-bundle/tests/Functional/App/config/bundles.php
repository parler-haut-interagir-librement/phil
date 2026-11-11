<?php

declare(strict_types=1);

use Phil\SitemapBundle\PhilSitemapBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilSitemapBundle::class => ['all' => true],
];
