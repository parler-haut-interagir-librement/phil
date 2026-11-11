<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Application;

use Phil\QrCode\ErrorCorrection;

/**
 * The defaults of the configuration, which each code may override: the level of error correction, the quiet zone,
 * the colours, the size of an SVG and the scale of a PNG, the ECI header and the kanji mode, and the longest payload
 * accepted.
 */
final readonly class QrCodeDefaults
{
    public function __construct(
        public ErrorCorrection $level = ErrorCorrection::Medium,
        public int $quietZone = 4,
        public string $dark = '#000000',
        public string $light = '#ffffff',
        public int $size = 0,
        public int $scale = 8,
        public bool $eci = true,
        public bool $kanji = true,
        public int $maxBytes = 2048,
    ) {
    }
}
