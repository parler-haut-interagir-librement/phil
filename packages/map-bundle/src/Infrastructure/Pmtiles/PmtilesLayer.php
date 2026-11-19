<?php

declare(strict_types=1);

namespace Phil\MapBundle\Infrastructure\Pmtiles;

use Override;
use Phil\MapBundle\Application\Contract\BaseLayer;
use Phil\MapBundle\Application\Tiles\BaseLayerDefinition;
use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;
use Psr\Log\LoggerInterface;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;

/**
 * The PMTiles adapter: tiles read in the browser from one archive the site serves, by range requests, with the
 * `pmtiles` package for raster archives and `protomaps-leaflet` for vector ones. Both are optional: when AssetMapper's
 * import map lacks the package, the map falls back on the other base layer, and says so in the log; the controller
 * falls back too when the package fails to load.
 */
final readonly class PmtilesLayer implements BaseLayer
{
    public const string RASTER = 'raster';

    public const string VECTOR = 'vector';

    private const array PACKAGES = [self::RASTER => 'pmtiles', self::VECTOR => 'protomaps-leaflet'];

    /**
     * @param string $kind `raster` or `vector`
     *
     * @throws InvalidMap when the kind is neither
     */
    public function __construct(
        private SiteAddress $archive,
        private string $kind,
        private string $attribution,
        private int $maxZoom,
        private string $flavor,
        private BaseLayer $fallback,
        private ?ImportMapConfigReader $importMap = null,
        private ?LoggerInterface $logger = null,
    ) {
        if (!isset(self::PACKAGES[$kind])) {
            throw InvalidMap::because(\sprintf('A PMTiles archive is "raster" or "vector", not "%s".', $kind));
        }
    }

    #[Override]
    public function definition(): BaseLayerDefinition
    {
        $fallback = $this->fallback->definition();
        $package = self::PACKAGES[$this->kind];

        if ($this->importMap instanceof ImportMapConfigReader && !$this->importMap->getEntries()->has($package)) {
            $this->logger?->warning('The PMTiles archive needs "{package}" in the import map: the map falls back on its other tiles.', ['package' => $package]);

            return $fallback;
        }

        // Symfony UX Map draws the fallback; the controller takes it away before the map is made, and gives it back
        // only if the archive cannot be read.
        return new BaseLayerDefinition($fallback->tileLayer, [...$fallback->extra, 'pmtiles' => [
            'url' => $this->archive->value,
            'kind' => $this->kind,
            'attribution' => $this->attribution,
            'maxZoom' => $this->maxZoom,
            'flavor' => $this->flavor,
        ]]);
    }
}
