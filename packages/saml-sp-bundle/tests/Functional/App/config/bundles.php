<?php

declare(strict_types=1);

use Phil\SamlSpBundle\PhilSamlSpBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;

return [
    FrameworkBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    PhilSamlSpBundle::class => ['all' => true],
];
