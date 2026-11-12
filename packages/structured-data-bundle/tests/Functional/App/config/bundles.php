<?php

declare(strict_types=1);

use Phil\MenuBundle\PhilMenuBundle;
use Phil\SocialCardsBundle\PhilSocialCardsBundle;
use Phil\StructuredDataBundle\PhilStructuredDataBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilMenuBundle::class => ['all' => true],
    PhilSocialCardsBundle::class => ['all' => true],
    PhilStructuredDataBundle::class => ['all' => true],
];
