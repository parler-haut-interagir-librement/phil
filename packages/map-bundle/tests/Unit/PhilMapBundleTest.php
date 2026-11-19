<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit;

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
use Phil\MapBundle\Domain\ValueObject\PrivacyGrid;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;
use Phil\MapBundle\Domain\ValueObject\TileSource;
use Phil\MapBundle\Infrastructure\Console\TileImageCommand;
use Phil\MapBundle\Infrastructure\DependencyInjection\BoothAvailabilityPass;
use Phil\MapBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\MapBundle\Infrastructure\DependencyInjection\MapServices;
use Phil\MapBundle\Infrastructure\Gd\GdImageTiler;
use Phil\MapBundle\Infrastructure\Http\TileProxyController;
use Phil\MapBundle\Infrastructure\Pmtiles\PmtilesLayer;
use Phil\MapBundle\Infrastructure\Reservation\ReservationBoothAvailability;
use Phil\MapBundle\Infrastructure\Twig\KitComponents;
use Phil\MapBundle\Infrastructure\Twig\MapExtension;
use Phil\MapBundle\PhilMapBundle;
use Phil\ReservationBundle\Application\Reservations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilMapBundle::class)]
#[CoversClass(MapServices::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(BoothAvailabilityPass::class)]
#[CoversClass(KitComponents::class)]
final class PhilMapBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);

        self::assertSame('phil_map', $this->extension()->getAlias());
        self::assertSame(ProxiedTiles::class, (string) $container->getAlias(BaseLayer::class));
        self::assertSame(UnknownBoothAvailability::class, (string) $container->getAlias(BoothAvailability::class));
        self::assertSame([PhilMapBundle::DEFAULT_TILES, PhilMapBundle::DEFAULT_ATTRIBUTION, 19, false], $container->getDefinition(TileSource::class)->getArguments());
        $proxy = $container->getDefinition(TileProxyController::class);
        self::assertEquals([new Reference(TileSource::class), new Reference('http_client'), new Reference('cache.app'), 'phil/map-bundle tile proxy', 604_800, 524_288, 5.0], $proxy->getArguments());
        self::assertTrue($proxy->isPublic());
        self::assertTrue($proxy->hasTag('controller.service_arguments'));
        self::assertSame([1000, 3], $container->getDefinition(PrivacyGrid::class)->getArguments());
        self::assertSame([40_000_000, 256], $container->getDefinition(GdImageTiler::class)->getArguments());
        self::assertSame([true], \array_slice($container->getDefinition(DirectTiles::class)->getArguments(), 1));
        self::assertSame([null], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertTrue($container->getDefinition(TileImageCommand::class)->hasTag('console.command'));

        self::assertSame([TileSource::class, 'of'], $container->getDefinition(TileSource::class)->getFactory());
        self::assertEquals([new Reference(TileSource::class), new Reference('router')], $container->getDefinition(ProxiedTiles::class)->getArguments());
        self::assertTrue($container->hasDefinition(NoTiles::class));
        self::assertSame([PrivacyGrid::class, 'of'], $container->getDefinition(PrivacyGrid::class)->getFactory());
        $neighbourhood = $container->getDefinition(Neighbourhood::class);
        self::assertEquals([new Reference(PrivacyGrid::class)], $neighbourhood->getArguments());
        self::assertTrue($neighbourhood->isPublic());
        self::assertTrue($container->hasDefinition(UnknownBoothAvailability::class));
        self::assertEquals([new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE)], $container->getDefinition(MapMessages::class)->getArguments());
        $maps = $container->getDefinition(Maps::class);
        self::assertEquals([new Reference(BaseLayer::class), new Reference(Neighbourhood::class), new Reference(BoothAvailability::class), new Reference(MapMessages::class)], $maps->getArguments());
        self::assertTrue($maps->isPublic());
        self::assertTrue($container->getAlias(ImageTiler::class)->isPublic());
        self::assertSame(GdImageTiler::class, (string) $container->getAlias(ImageTiler::class));
        self::assertEquals([new Reference(ImageTiler::class)], $container->getDefinition(TileImageCommand::class)->getArguments());
        $extension = $container->getDefinition(MapExtension::class);
        self::assertEquals([new Reference(KitComponents::class)], $extension->getArguments());
        self::assertTrue($extension->hasTag('twig.attribute_extension'));
        self::assertTrue($extension->hasTag('twig.runtime'));
    }

    public function testTheLimitsOfTheConfiguration(): void
    {
        foreach ([
            [['tiles' => ['max_zoom' => 0]], ['tiles' => ['max_zoom' => -1]], 'too small'],
            [['tiles' => ['max_zoom' => 22]], ['tiles' => ['max_zoom' => 23]], 'too big'],
            [['tiles' => ['proxy' => ['ttl' => 60]]], ['tiles' => ['proxy' => ['ttl' => 59]]], 'too small'],
            [['tiles' => ['proxy' => ['ttl' => 31_536_000]]], ['tiles' => ['proxy' => ['ttl' => 31_536_001]]], 'too big'],
            [['tiles' => ['proxy' => ['max_bytes' => 1024]]], ['tiles' => ['proxy' => ['max_bytes' => 1023]]], 'too small'],
            [['tiles' => ['proxy' => ['max_bytes' => 16_777_216]]], ['tiles' => ['proxy' => ['max_bytes' => 16_777_217]]], 'too big'],
            [['tiles' => ['pmtiles' => ['max_zoom' => 0]]], ['tiles' => ['pmtiles' => ['max_zoom' => -1]]], 'too small'],
            [['tiles' => ['pmtiles' => ['max_zoom' => 22]]], ['tiles' => ['pmtiles' => ['max_zoom' => 23]]], 'too big'],
            [['neighbourhood' => ['minimum' => 1]], ['neighbourhood' => ['minimum' => 0]], 'too small'],
            [['image_tiles' => ['max_pixels' => 1]], ['image_tiles' => ['max_pixels' => 0]], 'too small'],
            [['image_tiles' => ['max_pixels' => 500_000_000]], ['image_tiles' => ['max_pixels' => 500_000_001]], 'too big'],
            [['image_tiles' => ['tile_size' => 64]], ['image_tiles' => ['tile_size' => 63]], 'too small'],
            [['image_tiles' => ['tile_size' => 1024]], ['image_tiles' => ['tile_size' => 1025]], 'too big'],
        ] as [$accepted, $refused, $message]) {
            self::assertInstanceOf(ContainerBuilder::class, $this->load([$accepted]));

            try {
                $this->load([$refused]);
                self::fail('Refused: '.json_encode($refused));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString($message, $invalidConfigurationException->getMessage(), (string) json_encode($refused));
            }
        }
    }

    public function testTheKindsOfAnArchive(): void
    {
        foreach (['raster', 'vector'] as $kind) {
            $container = $this->load([['tiles' => ['source' => 'pmtiles', 'pmtiles' => ['url' => '/a.pmtiles', 'kind' => $kind]]]]);

            self::assertSame($kind, $container->getDefinition(PmtilesLayer::class)->getArgument(1));
        }

        self::assertSame(15, $this->load([['tiles' => ['source' => 'pmtiles', 'pmtiles' => ['url' => '/a.pmtiles']]]])->getDefinition(PmtilesLayer::class)->getArgument(3));
        self::assertSame('raster', $this->load([['tiles' => ['source' => 'pmtiles', 'pmtiles' => ['url' => '/a.pmtiles']]]])->getDefinition(PmtilesLayer::class)->getArgument(1));
        self::assertSame([SiteAddress::class, 'of'], $this->load([['tiles' => ['source' => 'pmtiles', 'pmtiles' => ['url' => '/a.pmtiles']]]])->getDefinition('phil_map.pmtiles_archive')->getFactory());
    }

    public function testEveryOption(): void
    {
        $container = $this->load([[
            'tiles' => [
                'source' => 'direct',
                'url' => 'http://localhost:8080/{z}/{x}/{y}.png',
                'attribution' => '© Local',
                'max_zoom' => 12,
                'allow_localhost' => true,
                'anonymous' => false,
                'proxy' => ['cache' => 'cache.tiles', 'ttl' => 60, 'user_agent' => 'ua', 'timeout' => 1.5, 'max_bytes' => 2048],
            ],
            'neighbourhood' => ['cell_metres' => 250, 'minimum' => 5],
            'booths' => ['availability' => 'app.booths'],
            'image_tiles' => ['max_pixels' => 1000, 'tile_size' => 512],
            'kit_subdirectory' => 'kit',
        ]]);

        self::assertSame(DirectTiles::class, (string) $container->getAlias(BaseLayer::class));
        self::assertSame('app.booths', (string) $container->getAlias(BoothAvailability::class));
        self::assertSame(['http://localhost:8080/{z}/{x}/{y}.png', '© Local', 12, true], $container->getDefinition(TileSource::class)->getArguments());
        self::assertEquals([new Reference('cache.tiles'), 'ua', 60, 2048, 1.5], \array_slice($container->getDefinition(TileProxyController::class)->getArguments(), 2));
        self::assertSame([false], \array_slice($container->getDefinition(DirectTiles::class)->getArguments(), 1));
        self::assertSame([250, 5], $container->getDefinition(PrivacyGrid::class)->getArguments());
        self::assertSame([1000, 512], $container->getDefinition(GdImageTiler::class)->getArguments());
        self::assertSame(['kit'], $container->getDefinition(KitComponents::class)->getArguments());
    }

    public function testNoTiles(): void
    {
        self::assertSame(NoTiles::class, (string) $this->load([['tiles' => ['source' => 'none']]])->getAlias(BaseLayer::class));
    }

    public function testAPmtilesArchive(): void
    {
        $container = $this->load([['tiles' => ['source' => 'pmtiles', 'pmtiles' => ['url' => '/maps/world.pmtiles', 'kind' => 'vector', 'max_zoom' => 13, 'flavor' => 'dark']]]]);

        self::assertSame(PmtilesLayer::class, (string) $container->getAlias(BaseLayer::class));
        self::assertSame(['/maps/world.pmtiles', false], $container->getDefinition('phil_map.pmtiles_archive')->getArguments());
        $arguments = $container->getDefinition(PmtilesLayer::class)->getArguments();
        self::assertEquals([new Reference('phil_map.pmtiles_archive'), 'vector', PhilMapBundle::DEFAULT_ATTRIBUTION, 13, 'dark', new Reference(ProxiedTiles::class)], \array_slice($arguments, 0, 6));
        self::assertEquals([new Reference('asset_mapper.importmap.config_reader', ContainerInterface::NULL_ON_INVALID_REFERENCE), new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE)], \array_slice($arguments, 6));
        self::assertSame('© Archive', $this->load([['tiles' => ['source' => 'pmtiles', 'pmtiles' => ['url' => '/a.pmtiles', 'attribution' => '© Archive']]]])->getDefinition(PmtilesLayer::class)->getArgument(2));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'a template without placeholder' => [['tiles' => ['url' => 'https://t.example.org/tiles']], 'see TileSource'];
        yield 'localhost not allowed' => [['tiles' => ['url' => 'http://localhost/{z}/{x}/{y}']], 'see TileSource'];
        yield 'an archive missing' => [['tiles' => ['source' => 'pmtiles']], 'needs tiles.pmtiles.url'];
        yield 'an archive elsewhere' => [['tiles' => ['source' => 'pmtiles', 'pmtiles' => ['url' => '//evil.example/a.pmtiles']]], 'needs tiles.pmtiles.url'];
        yield 'an unknown source' => [['tiles' => ['source' => 'google']], 'tiles.source'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refused')]
    public function testARefusedConfiguration(array $config, string $message): void
    {
        try {
            $this->load([$config]);
            self::fail('The configuration is refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertStringContainsString($message, $invalidConfigurationException->getMessage());
        }
    }

    public function testAnArchiveOnLocalhostWhenAllowed(): void
    {
        self::assertSame(['http://localhost/a.pmtiles', true], $this->load([['tiles' => ['source' => 'pmtiles', 'allow_localhost' => true, 'url' => 'http://localhost/{z}/{x}/{y}', 'pmtiles' => ['url' => 'http://localhost/a.pmtiles']]]])->getDefinition('phil_map.pmtiles_archive')->getArguments());
    }

    public function testTheBoothsOfPhilReservationBundle(): void
    {
        $container = $this->load([]);
        $container->register(Reservations::class, Reservations::class);
        new BoothAvailabilityPass()->process($container);

        self::assertSame(ReservationBoothAvailability::class, (string) $container->getAlias(BoothAvailability::class));
        self::assertEquals([new Reference(Reservations::class)], $container->getDefinition(ReservationBoothAvailability::class)->getArguments());
    }

    public function testTheBoothsOfTheApplicationOrNoneStay(): void
    {
        $configured = $this->load([['booths' => ['availability' => 'app.booths']]]);
        $configured->register(Reservations::class, Reservations::class);
        new BoothAvailabilityPass()->process($configured);
        self::assertSame('app.booths', (string) $configured->getAlias(BoothAvailability::class));

        $without = $this->load([]);
        new BoothAvailabilityPass()->process($without);
        self::assertSame(UnknownBoothAvailability::class, (string) $without->getAlias(BoothAvailability::class));

        $nothing = new ContainerBuilder();
        $nothing->register(Reservations::class, Reservations::class);
        new BoothAvailabilityPass()->process($nothing);
        self::assertFalse($nothing->hasAlias(BoothAvailability::class));
    }

    public function testThePrependedConfiguration(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->registerExtension($this->named('asset_mapper'));
        $container->registerExtension($this->named('ux_map'));

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $extension->prepend($container);

        self::assertSame([['paths' => [PhilMapBundle::CONTROLLERS_DIR => '@phil/map-bundle', PhilMapBundle::STYLES_DIR => 'phil-map']]], $container->getExtensionConfig('asset_mapper'));
        self::assertSame([['renderer' => 'leaflet://default']], $container->getExtensionConfig('ux_map'));
    }

    public function testNothingIsPrependedWithoutTheBundles(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        $extension = $this->extension();
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);

        $extension->prepend($container);

        self::assertSame([], $container->getExtensions());
    }

    public function testTheKitIsNamedAfterTheToolkit(): void
    {
        $container = $this->load([]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui/kit');
        new KitSubdirectoryPass()->process($container);

        self::assertSame(['ui/kit'], $container->getDefinition(KitComponents::class)->getArguments());
        self::assertSame('ui:kit:Item', new KitComponents('/ui/kit/')->name('Item'));
        self::assertSame('Item', new KitComponents()->name('Item'));

        $named = $this->load([['kit_subdirectory' => 'mine']]);
        $named->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($named);
        self::assertSame(['mine'], $named->getDefinition(KitComponents::class)->getArguments());

        $without = $this->load([]);
        new KitSubdirectoryPass()->process($without);
        self::assertSame([''], $without->getDefinition(KitComponents::class)->getArguments());

        $nothing = new ContainerBuilder();
        new KitSubdirectoryPass()->process($nothing);
        self::assertFalse($nothing->hasDefinition(KitComponents::class));
    }

    public function testTheBundleAddsItsPass(): void
    {
        $container = new ContainerBuilder();
        new PhilMapBundle()->build($container);

        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof KitSubdirectoryPass));
        self::assertCount(1, array_filter($container->getCompilerPassConfig()->getPasses(), static fn (object $pass): bool => $pass instanceof BoothAvailabilityPass));
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    private function load(array $configs): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load($configs, $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilMapBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }

    private function named(string $alias): ExtensionInterface
    {
        return new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
