<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application\Tiles;

use Symfony\UX\Map\Bridge\Leaflet\Option\TileLayer;

/**
 * What a base layer gives a map: the tile layer Symfony UX Map draws, or none, and what the bundle's controller reads
 * under the map's `extra.phil` — a PMTiles archive, and the layer to fall back on.
 */
final readonly class BaseLayerDefinition
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public TileLayer|false $tileLayer,
        public array $extra = [],
    ) {
    }
}
