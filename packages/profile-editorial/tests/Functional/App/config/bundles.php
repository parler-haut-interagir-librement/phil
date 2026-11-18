<?php

declare(strict_types=1);

use Phil\ContentWarningsBundle\PhilContentWarningsBundle;
use Phil\MarkupBundle\PhilMarkupBundle;
use Phil\StructuredDataBundle\PhilStructuredDataBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

/*
 * The bundles an application registers once it has installed phil/profile-editorial: Symfony Flex registers the same
 * bundles.
 */
return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilMarkupBundle::class => ['all' => true],
    PhilContentWarningsBundle::class => ['all' => true],
    PhilStructuredDataBundle::class => ['all' => true],
];
