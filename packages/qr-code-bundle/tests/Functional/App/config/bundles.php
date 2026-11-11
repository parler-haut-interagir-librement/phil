<?php

declare(strict_types=1);

use Phil\QrCodeBundle\PhilQrCodeBundle;
use Phil\SvgBlocksBundle\PhilSvgBlocksBundle;
use Phil\UjoBundle\PhilUjoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\UX\TwigComponent\TwigComponentBundle;

// The blocks of UJO documents are an optional integration: their bundles are registered only when installed.
$blocks = class_exists(PhilSvgBlocksBundle::class) ? [PhilUjoBundle::class => ['all' => true], PhilSvgBlocksBundle::class => ['all' => true]] : [];

return [
    FrameworkBundle::class => ['all' => true],
    TwigBundle::class => ['all' => true],
    TwigComponentBundle::class => ['all' => true],
    ...$blocks,
    PhilQrCodeBundle::class => ['all' => true],
];
