<?php

declare(strict_types=1);

use ApiPlatform\Symfony\Bundle\ApiPlatformBundle;
use Nelmio\ApiDocBundle\NelmioApiDocBundle;
use Phil\EnumsBundle\PhilEnumsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    ApiPlatformBundle::class => ['all' => true],
    NelmioApiDocBundle::class => ['all' => true],
    MakerBundle::class => ['all' => true],
    PhilEnumsBundle::class => ['all' => true],
];
