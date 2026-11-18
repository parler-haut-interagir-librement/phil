<?php

declare(strict_types=1);

use Phil\PagedMediaBundle\PhilPagedMediaBundle;
use Phil\Slides\PhilSlidesBundle;
use Phil\UjoBundle\PhilUjoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    PhilUjoBundle::class => ['all' => true],
    PhilPagedMediaBundle::class => ['all' => true],
    PhilSlidesBundle::class => ['all' => true],
];
