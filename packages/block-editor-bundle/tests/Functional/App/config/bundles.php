<?php

declare(strict_types=1);

use Phil\BlockEditorBundle\PhilBlockEditorBundle;
use Phil\UjoBundle\PhilUjoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;

return [
    FrameworkBundle::class => ['all' => true],
    SecurityBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    PhilUjoBundle::class => ['all' => true],
    PhilBlockEditorBundle::class => ['all' => true],
];
