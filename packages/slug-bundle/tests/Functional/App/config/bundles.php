<?php

declare(strict_types=1);

use Phil\SlugBundle\PhilSlugBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilSlugBundle::class => ['all' => true],
];
