<?php

declare(strict_types=1);

use Phil\UxKit\PhilUxKitBundle;
use Phil\VideoBundle\PhilVideoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigExtraBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    PhilUxKitBundle::class => ['all' => true],
    PhilVideoBundle::class => ['all' => true],
];
