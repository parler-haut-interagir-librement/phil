<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

/**
 * An image drawn in its box, scaled with its proportions kept: an artwork that fills the zone, or an object placed at
 * one position among others.
 */
final readonly class ImageItem implements Item
{
    public function __construct(
        public Raster $image,
        public Box $box,
        public Fit $fit = Fit::Contain,
        public HorizontalAlign $horizontal = HorizontalAlign::Centre,
        public VerticalAlign $vertical = VerticalAlign::Middle,
    ) {
    }

    public function box(): Box
    {
        return $this->box;
    }
}
