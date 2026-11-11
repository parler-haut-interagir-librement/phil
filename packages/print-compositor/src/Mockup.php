<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Phil\PrintCompositor\Exception\InvalidLayout;

/**
 * A face photo and the artworks placed on it, with the strength of the fabric's shading over them: 0 lays the artwork
 * flat on the photo, 1 darkens and lightens it as much as the photo's folds and shadows darken and lighten the fabric.
 */
final readonly class Mockup
{
    public const float DEFAULT_SHADING = 0.8;

    /**
     * @param list<Placement> $placements
     */
    public function __construct(
        public FacePhoto $photo,
        public array $placements,
        public float $shading = self::DEFAULT_SHADING,
    ) {
        if ($shading < 0 || $shading > 1) {
            throw InvalidLayout::because(\sprintf('A shading strength goes from 0 to 1; %s given.', $shading));
        }

        foreach ($placements as $placement) {
            $photo->zone($placement->zone);
        }
    }
}
