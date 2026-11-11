<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\GeoBundle\Infrastructure\Form\CoordinatesType;
use Phil\GeoBundle\Infrastructure\Twig\GeoExtension;
use Phil\GeoBundle\PhilGeoBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

#[CoversClass(PhilGeoBundle::class)]
final class PhilGeoBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilGeo(): void
    {
        self::assertSame('phil_geo', $this->extension()->getAlias());
    }

    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([]);

        self::assertSame(['service_container', CoordinatesType::class, GeoExtension::class], array_keys($container->getDefinitions()));
        self::assertTrue($container->getDefinition(CoordinatesType::class)->hasTag('form.type'));
        self::assertTrue($container->getDefinition(GeoExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(GeoExtension::class)->hasTag('twig.runtime'));
    }

    public function testTheEmbeddableIsPrependedToDoctrine(): void
    {
        $container = $this->prepend([], [['orm' => []]]);
        $bundle = new PhilGeoBundle();

        self::assertSame([
            ['orm' => []],
            ['orm' => ['mappings' => ['PhilGeo' => [
                'type' => 'xml',
                'is_bundle' => false,
                'dir' => $bundle->getPath().'/config/doctrine',
                'prefix' => 'Phil\Geo',
            ]]]],
        ], $container->getExtensionConfig('doctrine'));
        self::assertFileExists($bundle->getPath().'/config/doctrine/Coordinates.orm.xml');
    }

    public function testWithoutTheOrmNothingIsPrepended(): void
    {
        self::assertSame([['dbal' => []]], $this->prepend([], [['dbal' => []]])->getExtensionConfig('doctrine'));
    }

    public function testTheEmbeddableCanBeLeftToTheApplication(): void
    {
        self::assertSame([['orm' => []]], $this->prepend([['doctrine_mapping' => false]], [['orm' => []]])->getExtensionConfig('doctrine'));
        self::assertCount(2, $this->prepend([['doctrine_mapping' => true]], [['orm' => []]])->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineNothingIsPrepended(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);
        // A configuration the bundle refuses is not even read.
        $container->prependExtensionConfig('phil_geo', ['unknown' => true]);

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['unknown' => true]], $container->getExtensionConfig('phil_geo'));
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

    /**
     * @param list<array<string, mixed>> $configs
     * @param list<array<string, mixed>> $doctrine
     */
    private function prepend(array $configs, array $doctrine): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());

        foreach ($doctrine as $config) {
            $container->loadFromExtension('doctrine', $config);
        }

        $extension = $this->extension();
        $container->registerExtension($extension);

        foreach ($configs as $config) {
            $container->loadFromExtension('phil_geo', $config);
        }

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilGeoBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
