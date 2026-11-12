<?php

declare(strict_types=1);

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Phil\FormsAutosave\PhilFormsAutosaveBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\KeyManagement\KeyManagementBundle;

return [
    FrameworkBundle::class => ['all' => true],
    DoctrineBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    KeyManagementBundle::class => ['all' => true],
    PhilFormsAutosaveBundle::class => ['all' => true],
];
