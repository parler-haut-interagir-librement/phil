<?php

declare(strict_types=1);

use Phil\PagedMediaBundle\PhilPagedMediaBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilPagedMediaBundle::class => ['all' => true],
];
