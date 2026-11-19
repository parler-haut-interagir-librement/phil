<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Fixtures;

use Phil\MapBundle\Application\Contract\BaseLayer;
use Phil\MapBundle\Application\Tiles\BaseLayerDefinition;
use Symfony\UX\Map\Bridge\Leaflet\Option\TileLayer;

/**
 * A base layer that gives what it was given.
 */
final readonly class FixedBaseLayer implements BaseLayer
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        private TileLayer|false $tileLayer = false,
        private array $extra = [],
    ) {
    }

    public function definition(): BaseLayerDefinition
    {
        return new BaseLayerDefinition($this->tileLayer, $this->extra);
    }
}
