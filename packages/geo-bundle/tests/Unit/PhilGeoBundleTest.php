<?php

declare(strict_types=1);

namespace Phil\GeoBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\Geo\Coordinates;
use Phil\GeoBundle\Infrastructure\Doctrine\CoordinatesMappingDriver;
use Phil\GeoBundle\Infrastructure\Form\CoordinatesType;
use Phil\GeoBundle\Infrastructure\Twig\GeoExtension;
use Phil\GeoBundle\PhilGeoBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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

    public function testTheEmbeddableIsTurnedOnBesideTheOrm(): void
    {
        $container = $this->prepend([], [['orm' => []]]);

        self::assertTrue($container->getParameter(PhilGeoBundle::DOCTRINE_MAPPING));
        // Nothing is prepended to DoctrineBundle: the driver joins its chain through the compiler pass.
        self::assertSame([['orm' => []]], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutTheOrmNothingIsTurnedOn(): void
    {
        $container = $this->prepend([], [['dbal' => []]]);

        self::assertFalse($container->hasParameter(PhilGeoBundle::DOCTRINE_MAPPING));
        self::assertSame([['dbal' => []]], $container->getExtensionConfig('doctrine'));
    }

    public function testTheEmbeddableCanBeLeftToTheApplication(): void
    {
        self::assertFalse($this->prepend([['doctrine_mapping' => false]], [['orm' => []]])->hasParameter(PhilGeoBundle::DOCTRINE_MAPPING));
        self::assertTrue($this->prepend([['doctrine_mapping' => true]], [['orm' => []]])->hasParameter(PhilGeoBundle::DOCTRINE_MAPPING));
    }

    public function testTheDriverJoinsTheChainOfTheDefaultEntityManagerForCoordinatesAlone(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.default_entity_manager', 'default');
        $container->register('doctrine.orm.default_metadata_driver');
        $container->setParameter(PhilGeoBundle::DOCTRINE_MAPPING, true);
        new PhilGeoBundle()->build($container);
        $this->runMappingPasses($container);

        $calls = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertIsArray($calls[0]);
        [$method, $arguments] = $calls[0];
        self::assertSame('addDriver', $method);
        self::assertIsArray($arguments);
        [$driver, $namespace] = $arguments;
        self::assertSame(Coordinates::class, $namespace);
        self::assertInstanceOf(Definition::class, $driver);
        self::assertSame(CoordinatesMappingDriver::class, $driver->getClass());
    }

    public function testTheDriverStaysOutWhileTheMappingIsOff(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.default_entity_manager', 'default');
        $container->register('doctrine.orm.default_metadata_driver');
        new PhilGeoBundle()->build($container);
        $this->runMappingPasses($container);

        self::assertSame([], $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
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

    private function runMappingPasses(ContainerBuilder $container): void
    {
        $passes = array_filter($container->getCompilerPassConfig()->getPasses(), static fn (CompilerPassInterface $pass): bool => $pass instanceof DoctrineOrmMappingsPass);
        self::assertCount(1, $passes);

        foreach ($passes as $pass) {
            $pass->process($container);
        }
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
