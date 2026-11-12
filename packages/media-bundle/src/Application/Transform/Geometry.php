<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application\Transform;

/**
 * An image as transformations see it: its size in pixels and its focal point, from 0 to 1 across and down.
 */
final readonly class Geometry
{
    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private function __construct(
        public int $width,
        public int $height,
        public float $focalX,
        public float $focalY,
    ) {
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function of(int $width, int $height, float $focalX = 0.5, float $focalY = 0.5): self
    {
        return new self($width, $height, min(max($focalX, 0.0), 1.0), min(max($focalY, 0.0), 1.0));
    }
}
