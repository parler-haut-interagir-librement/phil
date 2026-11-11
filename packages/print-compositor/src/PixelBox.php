<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Phil\PrintCompositor\Exception\InvalidLayout;

/**
 * A rectangle in pixels, measured from the top left corner of an image.
 */
final readonly class PixelBox
{
    public function __construct(
        public int $x,
        public int $y,
        public int $width,
        public int $height,
    ) {
        if ($x < 0 || $y < 0) {
            throw InvalidLayout::because(\sprintf('A pixel box starts inside its image; it starts at %d × %d.', $x, $y));
        }

        if ($width < 1 || $height < 1) {
            throw InvalidLayout::because(\sprintf('A pixel box has a positive size; %d × %d given.', $width, $height));
        }
    }

    /**
     * Whether the box lies inside an image of the given size, edges included.
     */
    public function isInside(int $width, int $height): bool
    {
        return $this->x + $this->width <= $width && $this->y + $this->height <= $height;
    }
}
