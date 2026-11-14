<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\Toolkit\UXToolkitBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

// The bundles of InstallKernel.
return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    UXToolkitBundle::class => ['all' => true],
];
