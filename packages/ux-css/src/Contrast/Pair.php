<?php

declare(strict_types=1);

namespace Phil\UxCss\Contrast;

/**
 * Two properties that are seen one on the other, and the contrast they must reach in both schemes.
 */
final readonly class Pair
{
    public function __construct(
        public string $foreground,
        public string $background,
        public float $minimum,
    ) {
    }
}
