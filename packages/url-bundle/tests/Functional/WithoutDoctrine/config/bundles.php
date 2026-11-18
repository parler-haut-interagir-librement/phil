<?php

declare(strict_types=1);

use Phil\UrlBundle\PhilUrlBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilUrlBundle::class => ['all' => true],
];
