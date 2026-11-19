<?php

declare(strict_types=1);

namespace Phil\MapBundle;

use Override;
use Phil\MapBundle\Domain\Enum\TileMode;
use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\ValueObject\PrivacyGrid;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;
use Phil\MapBundle\Domain\ValueObject\TileSource;
use Phil\MapBundle\Infrastructure\DependencyInjection\BoothAvailabilityPass;
use Phil\MapBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\MapBundle\Infrastructure\DependencyInjection\MapServices;
use Phil\MapBundle\Infrastructure\Gd\GdImageTiler;
use Phil\MapBundle\Infrastructure\Pmtiles\PmtilesLayer;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

/**
 * Maps on Symfony UX Map and its Leaflet bridge: the tiles proxied through the site by default, or loaded directly,
 * or read from a self-hosted PMTiles archive; places from phil/geo as markers; the neighbourhood as areas of a privacy
 * grid; floor plans whose booths are resources of phil/reservation; large images cut into tiles with GD. With
 * AssetMapper, the controller and the stylesheet are served without a build step.
 *
 * ```yaml
 * phil_map:
 *     tiles:
 *         source: proxy
 *         proxy:
 *             user_agent: 'example.org maps (contact@example.org)'
 * ```
 *
 * @phpstan-import-type Config from MapServices
 */
final class PhilMapBundle extends AbstractBundle
{
    public const string CONTROLLERS_DIR = __DIR__.'/../assets/src';

    public const string STYLES_DIR = __DIR__.'/../assets/styles';

    public const string DEFAULT_TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';

    public const string DEFAULT_ATTRIBUTION = '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

    #[Override]
    protected string $extensionAlias = 'phil_map';

    #[Override]
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->validate()->ifTrue($this->isInvalidSource(...))->thenInvalid('The tile source of %s is refused: see TileSource.')->end()
            ->validate()->ifTrue($this->lacksArchive(...))->thenInvalid('The PMTiles source of %s needs tiles.pmtiles.url, a path of the site or an address.')->end()
            ->children()
                ->arrayNode('tiles')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->enumNode('source')
                            ->info('How the browser gets the tiles: "proxy" through the site, "direct" from the source, "pmtiles" from an archive the site serves, or "none".')
                            ->values(array_column(TileMode::cases(), 'value'))
                            ->defaultValue(TileMode::Proxy->value)
                        ->end()
                        ->scalarNode('url')->info('The template of the tile source, with {z}, {x} and {y}.')->cannotBeEmpty()->defaultValue(self::DEFAULT_TILES)->end()
                        ->scalarNode('attribution')->info('The attribution the licence of the tiles requires, in HTML.')->cannotBeEmpty()->defaultValue(self::DEFAULT_ATTRIBUTION)->end()
                        ->integerNode('max_zoom')->info('The deepest zoom of the source.')->min(0)->max(TileSource::MAX_ZOOM)->defaultValue(19)->end()
                        ->booleanNode('allow_localhost')->info('Whether localhost is a host of the tile source and of the archive, in development and tests.')->defaultFalse()->end()
                        ->booleanNode('anonymous')->info('Whether direct tiles are fetched without cookies, as anonymous cross-origin requests the source answers with CORS headers.')->defaultTrue()->end()
                        ->arrayNode('proxy')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('cache')->info('The cache pool that keeps the tiles.')->cannotBeEmpty()->defaultValue('cache.app')->end()
                                ->integerNode('ttl')->info('How long a tile is kept and cached by browsers, in seconds.')->min(60)->max(31_536_000)->defaultValue(604_800)->end()
                                ->scalarNode('user_agent')->info('The user agent the proxy names itself with; tile policies ask for one that names the site and a contact.')->cannotBeEmpty()->defaultValue('phil/map-bundle tile proxy')->end()
                                ->floatNode('timeout')->info('Seconds the source has to answer.')->min(0.5)->max(60.0)->defaultValue(5.0)->end()
                                ->integerNode('max_bytes')->info('The largest tile accepted, in bytes.')->min(1024)->max(16_777_216)->defaultValue(524_288)->end()
                            ->end()
                        ->end()
                        ->arrayNode('pmtiles')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('url')->info('The archive, a path of the site or an address.')->defaultNull()->end()
                                ->enumNode('kind')->info('"raster" for an archive of images, read by pmtiles; "vector" for one of vector tiles, drawn by protomaps-leaflet.')->values([PmtilesLayer::RASTER, PmtilesLayer::VECTOR])->defaultValue(PmtilesLayer::RASTER)->end()
                                ->scalarNode('attribution')->info('The attribution of the archive; that of the source when null.')->defaultNull()->end()
                                ->integerNode('max_zoom')->info('The deepest zoom of the archive.')->min(0)->max(TileSource::MAX_ZOOM)->defaultValue(15)->end()
                                ->scalarNode('flavor')->info('The style of a vector archive, a flavor of protomaps-leaflet.')->cannotBeEmpty()->defaultValue('light')->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('neighbourhood')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('cell_metres')->info('The side of a cell of the privacy grid, in metres.')->min(PrivacyGrid::MIN_METRES)->max(PrivacyGrid::MAX_METRES)->defaultValue(1000)->end()
                        ->integerNode('minimum')->info('The fewest positions a cell holds to be shown.')->min(1)->max(PrivacyGrid::MAX_MINIMUM)->defaultValue(3)->end()
                    ->end()
                ->end()
                ->arrayNode('booths')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('availability')->info("The service, a BoothAvailability, that says the booths' states; phil/reservation-bundle's when it is enabled, else none, when null.")->defaultNull()->end()
                    ->end()
                ->end()
                ->arrayNode('image_tiles')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_pixels')->info('The largest image the tiler decodes, in pixels.')->min(1)->max(500_000_000)->defaultValue(GdImageTiler::MAX_PIXELS)->end()
                        ->integerNode('tile_size')->info('The side of a tile, in pixels.')->min(64)->max(1024)->defaultValue(GdImageTiler::TILE_SIZE)->end()
                    ->end()
                ->end()
                ->scalarNode('kit_subdirectory')->info('Where the recipes of the PHIL UX kit are installed; the subdirectory of ux_toolkit when null.')->defaultNull()->end()
            ->end();
    }

    #[Override]
    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Symfony UX Map draws with Leaflet, unless the application names another renderer.
        if ($builder->hasExtension('ux_map')) {
            $container->extension('ux_map', ['renderer' => 'leaflet://default']);
        }

        // AssetMapperBundle has its own extension since Symfony 8.2; `framework.asset_mapper` is only an alias of it.
        if ($builder->hasExtension('asset_mapper')) {
            $container->extension('asset_mapper', ['paths' => [
                self::CONTROLLERS_DIR => '@phil/map-bundle',
                self::STYLES_DIR => 'phil-map',
            ]]);
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Override]
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        /** @var Config $config */
        MapServices::register($builder, $config);
    }

    #[Override]
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new KitSubdirectoryPass());
        $container->addCompilerPass(new BoothAvailabilityPass());
    }

    private function isInvalidSource(mixed $config): bool
    {
        /** @var Config $config */
        try {
            TileSource::of($config['tiles']['url'], $config['tiles']['attribution'], $config['tiles']['max_zoom'], $config['tiles']['allow_localhost']);
        } catch (InvalidMap) {
            return true;
        }

        return false;
    }

    private function lacksArchive(mixed $config): bool
    {
        /** @var Config $config */
        if (TileMode::Pmtiles->value !== $config['tiles']['source']) {
            return false;
        }

        try {
            SiteAddress::of((string) $config['tiles']['pmtiles']['url'], $config['tiles']['allow_localhost']);
        } catch (InvalidMap) {
            return true;
        }

        return false;
    }
}
