<?php

declare(strict_types=1);

namespace Phil\MapBundle\Application\Tiles;

use Override;
use Phil\MapBundle\Application\Contract\BaseLayer;
use Phil\MapBundle\Domain\ValueObject\TileSource;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\UX\Map\Bridge\Leaflet\Option\TileLayer;

/**
 * The tiles through the site's own route, which fetches them from the source and keeps them: the source sees the
 * site, never the visitor. The default base layer.
 */
final readonly class ProxiedTiles implements BaseLayer
{
    public const string ROUTE = 'phil_map_tile';

    private const string SAMPLE = '/0/0/0';

    public function __construct(
        private TileSource $source,
        private UrlGeneratorInterface $urls,
    ) {
    }

    #[Override]
    public function definition(): BaseLayerDefinition
    {
        // The route's path ends with its three numbers: the template is the path of tile 0/0/0 with placeholders.
        $path = $this->urls->generate(self::ROUTE, ['z' => 0, 'x' => 0, 'y' => 0]);
        $template = substr($path, 0, -\strlen(self::SAMPLE)).'/{z}/{x}/{y}';

        return new BaseLayerDefinition(new TileLayer($template, $this->source->attribution, [
            'maxZoom' => $this->source->maxZoom,
            'referrerPolicy' => 'no-referrer',
        ]));
    }
}
