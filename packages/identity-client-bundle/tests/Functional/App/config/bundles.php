<?php

declare(strict_types=1);

use Phil\IdentityClientBundle\PhilIdentityClientBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;

return [
    FrameworkBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    PhilIdentityClientBundle::class => ['all' => true],
];
