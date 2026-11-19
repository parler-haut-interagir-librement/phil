<?php

declare(strict_types=1);

namespace Phil\MapBundle\Infrastructure\DependencyInjection;

use Phil\MapBundle\Application\Contract\BaseLayer;
use Phil\MapBundle\Application\Contract\BoothAvailability;
use Phil\MapBundle\Application\Contract\ImageTiler;
use Phil\MapBundle\Application\MapMessages;
use Phil\MapBundle\Application\Maps;
use Phil\MapBundle\Application\Neighbourhood;
use Phil\MapBundle\Application\Tiles\DirectTiles;
use Phil\MapBundle\Application\Tiles\NoTiles;
use Phil\MapBundle\Application\Tiles\ProxiedTiles;
use Phil\MapBundle\Application\UnknownBoothAvailability;
use Phil\MapBundle\Domain\Enum\TileMode;
use Phil\MapBundle\Domain\ValueObject\PrivacyGrid;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;
use Phil\MapBundle\Domain\ValueObject\TileSource;
use Phil\MapBundle\Infrastructure\Console\TileImageCommand;
use Phil\MapBundle\Infrastructure\Gd\GdImageTiler;
use Phil\MapBundle\Infrastructure\Http\TileProxyController;
use Phil\MapBundle\Infrastructure\Pmtiles\PmtilesLayer;
use Phil\MapBundle\Infrastructure\Twig\KitComponents;
use Phil\MapBundle\Infrastructure\Twig\MapExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The bundle's services: the tile source and the base layer the configuration chooses, the tile proxy, the privacy
 * grid, the booths' availability — none by default, until BoothAvailabilityPass finds phil/reservation-bundle —, the tiler and its command with
 * GD, the maps and the Twig functions.
 *
 * @phpstan-type Config array{
 *     tiles: array{source: string, url: string, attribution: string, max_zoom: int, allow_localhost: bool, anonymous: bool,
 *         proxy: array{cache: string, ttl: int, user_agent: string, timeout: float, max_bytes: int},
 *         pmtiles: array{url: ?string, kind: string, attribution: ?string, max_zoom: int, flavor: string}},
 *     neighbourhood: array{cell_metres: int, minimum: int},
 *     booths: array{availability: ?string},
 *     image_tiles: array{max_pixels: int, tile_size: int},
 *     kit_subdirectory: ?string
 * }
 */
final class MapServices
{
    /**
     * @param Config $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        $tiles = $config['tiles'];
        $container->register(TileSource::class, TileSource::class)
            ->setFactory([TileSource::class, 'of'])
            ->setArguments([$tiles['url'], $tiles['attribution'], $tiles['max_zoom'], $tiles['allow_localhost']]);
        $container->register(TileProxyController::class, TileProxyController::class)
            ->setArguments([
                new Reference(TileSource::class),
                new Reference('http_client'),
                new Reference($tiles['proxy']['cache']),
                $tiles['proxy']['user_agent'],
                $tiles['proxy']['ttl'],
                $tiles['proxy']['max_bytes'],
                $tiles['proxy']['timeout'],
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(ProxiedTiles::class, ProxiedTiles::class)->setArguments([new Reference(TileSource::class), new Reference('router')]);
        $container->register(DirectTiles::class, DirectTiles::class)->setArguments([new Reference(TileSource::class), $tiles['anonymous']]);
        $container->register(NoTiles::class, NoTiles::class);
        $container->setAlias(BaseLayer::class, self::baseLayer($container, $config));

        $container->register(PrivacyGrid::class, PrivacyGrid::class)
            ->setFactory([PrivacyGrid::class, 'of'])
            ->setArguments([$config['neighbourhood']['cell_metres'], $config['neighbourhood']['minimum']]);
        $container->register(Neighbourhood::class, Neighbourhood::class)->setArguments([new Reference(PrivacyGrid::class)])->setPublic(true);

        $container->register(UnknownBoothAvailability::class, UnknownBoothAvailability::class);
        // BoothAvailabilityPass puts phil/reservation-bundle's in place of the default, once every bundle is loaded.
        $container->setParameter(BoothAvailabilityPass::PARAMETER, null === $config['booths']['availability']);
        $container->setAlias(BoothAvailability::class, $config['booths']['availability'] ?? UnknownBoothAvailability::class);

        $container->register(MapMessages::class, MapMessages::class)
            ->setArguments([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)]);
        $container->register(Maps::class, Maps::class)
            ->setArguments([new Reference(BaseLayer::class), new Reference(Neighbourhood::class), new Reference(BoothAvailability::class), new Reference(MapMessages::class)])
            ->setPublic(true);

        $container->register(GdImageTiler::class, GdImageTiler::class)
            ->setArguments([$config['image_tiles']['max_pixels'], $config['image_tiles']['tile_size']]);
        $container->setAlias(ImageTiler::class, GdImageTiler::class)->setPublic(true);
        $container->register(TileImageCommand::class, TileImageCommand::class)
            ->setArguments([new Reference(ImageTiler::class)])
            ->addTag('console.command');

        // The subdirectory of the Toolkit is read by KitSubdirectoryPass when the configuration names none.
        $container->register(KitComponents::class, KitComponents::class)->setArguments([$config['kit_subdirectory']]);
        $container->register(MapExtension::class, MapExtension::class)
            ->setArguments([new Reference(KitComponents::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');
    }

    /**
     * @param Config $config
     */
    private static function baseLayer(ContainerBuilder $container, array $config): string
    {
        $tiles = $config['tiles'];

        return match (TileMode::from($tiles['source'])) {
            TileMode::Proxy => ProxiedTiles::class,
            TileMode::Direct => DirectTiles::class,
            TileMode::None => NoTiles::class,
            TileMode::Pmtiles => self::pmtiles($container, $tiles),
        };
    }

    /**
     * @param Config['tiles'] $tiles
     */
    private static function pmtiles(ContainerBuilder $container, array $tiles): string
    {
        $pmtiles = $tiles['pmtiles'];
        $container->register('phil_map.pmtiles_archive', SiteAddress::class)
            ->setFactory([SiteAddress::class, 'of'])
            ->setArguments([(string) $pmtiles['url'], $tiles['allow_localhost']]);
        $container->register(PmtilesLayer::class, PmtilesLayer::class)->setArguments([
            new Reference('phil_map.pmtiles_archive'),
            $pmtiles['kind'],
            $pmtiles['attribution'] ?? $tiles['attribution'],
            $pmtiles['max_zoom'],
            $pmtiles['flavor'],
            new Reference(ProxiedTiles::class),
            new Reference('asset_mapper.importmap.config_reader', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE),
        ]);

        return PmtilesLayer::class;
    }
}
