<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\AiBundle\PhilAiBundle;
use Phil\UserSecretsBundle\PhilUserSecretsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\KeyManagement\KeyManagementBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    KeyManagementBundle::class => ['all' => true],
    PhilUserSecretsBundle::class => ['all' => true],
    PhilAiBundle::class => ['all' => true],
];
