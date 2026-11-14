<?php

declare(strict_types=1);

use Phil\AuditBundle\PhilAuditBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilAuditBundle::class => ['all' => true],
];
