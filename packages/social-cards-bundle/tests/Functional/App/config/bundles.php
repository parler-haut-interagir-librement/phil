<?php

declare(strict_types=1);

use Phil\SocialCardsBundle\PhilSocialCardsBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    PhilSocialCardsBundle::class => ['all' => true],
];
