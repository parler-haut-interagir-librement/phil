<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application\Tiles;

use Override;
use Phil\MapBundle\Application\Contract\BaseLayer;
use Phil\MapBundle\Domain\ValueObject\TileSource;
use Symfony\UX\Map\Bridge\Leaflet\Option\TileLayer;

/**
 * The tiles straight from the source, without referrer and, as anonymous cross-origin requests, without cookies: the
 * source still sees the visitor's address. Anonymous requests need the source to answer with CORS headers.
 */
final readonly class DirectTiles implements BaseLayer
{
    public function __construct(
        private TileSource $source,
        private bool $anonymous = true,
    ) {
    }

    #[Override]
    public function definition(): BaseLayerDefinition
    {
        $options = ['maxZoom' => $this->source->maxZoom, 'referrerPolicy' => 'no-referrer'];

        if ($this->anonymous) {
            $options['crossOrigin'] = 'anonymous';
        }

        return new BaseLayerDefinition(new TileLayer($this->source->template, $this->source->attribution, $options));
    }
}
