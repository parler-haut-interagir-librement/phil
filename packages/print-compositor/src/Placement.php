<?php

declare(strict_types=1);

namespace Phil\PrintCompositor;

/**
 * An artwork placed in a zone of a face photo, scaled to fit the zone's rectangle with its proportions kept. A print
 * file of the zone has the zone's proportions and fills it exactly.
 */
final readonly class Placement
{
    public function __construct(
        public string $zone,
        public Raster $artwork,
        public HorizontalAlign $horizontal = HorizontalAlign::Centre,
        public VerticalAlign $vertical = VerticalAlign::Top,
    ) {
    }
}
