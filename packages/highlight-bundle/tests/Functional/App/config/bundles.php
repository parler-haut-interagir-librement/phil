<?php

declare(strict_types=1);

use Phil\HighlightBundle\PhilHighlightBundle;
use Phil\UjoBundle\PhilUjoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilUjoBundle::class => ['all' => true],
    PhilHighlightBundle::class => ['all' => true],
];
