<?php

declare(strict_types=1);

namespace Phil\MapBundle\Domain\ValueObject;

/**
 * The tiles cut from an image: its size, the zoom at which a tile shows its own pixels, the side of a tile, and how
 * many were written.
 */
final readonly class TilePyramid
{
    public function __construct(
        public int $width,
        public int $height,
        public int $nativeZoom,
        public int $tileSize,
        public int $tiles,
    ) {
    }
}
