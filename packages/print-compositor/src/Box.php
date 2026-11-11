<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

use Phil\PrintCompositor\Exception\InvalidLayout;

/**
 * A rectangle in millimetres, measured from the top left corner of its zone.
 */
final readonly class Box
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {
        if ($x < 0 || $y < 0) {
            throw InvalidLayout::because(\sprintf('A box starts inside its zone; it starts at %s × %s mm.', $x, $y));
        }

        if ($width <= 0 || $height <= 0) {
            throw InvalidLayout::because(\sprintf('A box has a positive size; %s × %s mm given.', $width, $height));
        }
    }

    /**
     * Whether the box lies inside a zone of the given size, edges included.
     */
    public function isInside(float $width, float $height): bool
    {
        return $this->x + $this->width <= $width && $this->y + $this->height <= $height;
    }

    /**
     * The box in pixels at the given resolution, each edge rounded to the nearest pixel.
     */
    public function toPixels(int $dpi): PixelBox
    {
        $left = Units::pixels($this->x, $dpi);
        $top = Units::pixels($this->y, $dpi);

        return new PixelBox(
            $left,
            $top,
            max(1, Units::pixels($this->x + $this->width, $dpi) - $left),
            max(1, Units::pixels($this->y + $this->height, $dpi) - $top),
        );
    }
}
