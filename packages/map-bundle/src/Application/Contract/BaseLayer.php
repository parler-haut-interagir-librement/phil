<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application\Contract;

use Phil\MapBundle\Application\Tiles\BaseLayerDefinition;

/**
 * The tiles under a geographic map: proxied through the site, loaded directly from the source, read from a PMTiles
 * archive, or none. The `tiles.source` option chooses one; an application aliases its own.
 *
 * Invariant: a definition never sends the visitor's cookies or referrer to a host other than the site's.
 */
interface BaseLayer
{
    public function definition(): BaseLayerDefinition;
}
