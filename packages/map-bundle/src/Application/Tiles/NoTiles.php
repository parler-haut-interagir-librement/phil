<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application\Tiles;

use Override;
use Phil\MapBundle\Application\Contract\BaseLayer;

/**
 * No tiles: the markers and the shapes alone, on the map's background.
 */
final readonly class NoTiles implements BaseLayer
{
    #[Override]
    public function definition(): BaseLayerDefinition
    {
        return new BaseLayerDefinition(false);
    }
}
