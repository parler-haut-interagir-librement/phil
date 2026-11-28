<?php

declare(strict_types=1);

use Phil\SvgBlocksBundle\PhilSvgBlocksBundle;
use Phil\UjoBundle\PhilUjoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

return [
    FrameworkBundle::class => ['all' => true],
    PhilUjoBundle::class => ['all' => true],
    PhilSvgBlocksBundle::class => ['all' => true],
];
