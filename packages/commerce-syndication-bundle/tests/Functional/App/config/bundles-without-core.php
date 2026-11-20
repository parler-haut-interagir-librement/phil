<?php

declare(strict_types=1);

use Phil\CommerceSyndicationBundle\PhilCommerceSyndicationBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilCommerceSyndicationBundle::class => ['all' => true],
];
