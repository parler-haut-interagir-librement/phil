<?php

declare(strict_types=1);

namespace Phil\MeasurementBundle\Tests\Unit;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Types\TypeRegistry;
use Phil\Measurement\ChannelConversions;
use Phil\Measurement\Cldr\UnitFormatter;
use Phil\Measurement\Cldr\UnitPreferences;
use Phil\Measurement\Exception\MeasurementException;
use Phil\Measurement\UnitRegistry;
use Phil\MeasurementBundle\Infrastructure\Doctrine\MeasurementType as MeasurementColumnType;
use Phil\MeasurementBundle\Infrastructure\Form\MeasurementType;
use Phil\MeasurementBundle\Infrastructure\Twig\MeasurementExtension;
use Phil\MeasurementBundle\Infrastructure\Validator\MeasurementRangeValidator;
use Phil\MeasurementBundle\Infrastructure\Validator\MeasurementUnitValidator;
use Phil\MeasurementBundle\PhilMeasurementBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilMeasurementBundle::class)]
final class PhilMeasurementBundleTest extends TestCase
{
    public function testTheAlias(): void
    {
        self::assertSame('phil_measurement', $this->extension()->getAlias());
    }

    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([]);

        self::assertSame([
            'service_container',
            'phil_measurement.standard_registry',
            UnitRegistry::class,
            ChannelConversions::class,
            UnitPreferences::class,
            UnitFormatter::class,
            MeasurementType::class,
            MeasurementRangeValidator::class,
            MeasurementUnitValidator::class,
            MeasurementExtension::class,
        ], array_keys($container->getDefinitions()));

        self::assertSame([UnitRegistry::class, 'standard'], $container->getDefinition('phil_measurement.standard_registry')->getFactory());
        self::assertEquals([new Reference('phil_measurement.standard_registry'), 'withDefinitions'], $container->getDefinition(UnitRegistry::class)->getFactory());
        self::assertSame([[]], $container->getDefinition(UnitRegistry::class)->getArguments());
        self::assertTrue($container->getAlias(PhilMeasurementBundle::REGISTRY)->isPublic());
        self::assertSame(UnitRegistry::class, (string) $container->getAlias(PhilMeasurementBundle::REGISTRY));
        self::assertEquals([new Reference(UnitRegistry::class), []], $container->getDefinition(ChannelConversions::class)->getArguments());
        self::assertSame([UnitPreferences::class, 'cldr'], $container->getDefinition(UnitPreferences::class)->getFactory());
        self::assertEquals([new Reference(UnitRegistry::class)], $container->getDefinition(UnitPreferences::class)->getArguments());
        self::assertSame([UnitFormatter::class, 'cldr'], $container->getDefinition(UnitFormatter::class)->getFactory());
        self::assertEquals([new Reference(UnitRegistry::class), new Reference(ChannelConversions::class), new Reference(UnitFormatter::class)], $container->getDefinition(MeasurementType::class)->getArguments());
        self::assertTrue($container->getDefinition(MeasurementType::class)->hasTag('form.type'));
        self::assertEquals([new Reference(UnitRegistry::class)], $container->getDefinition(MeasurementRangeValidator::class)->getArguments());
        self::assertTrue($container->getDefinition(MeasurementRangeValidator::class)->hasTag('validator.constraint_validator'));
        self::assertTrue($container->getDefinition(MeasurementUnitValidator::class)->hasTag('validator.constraint_validator'));
        self::assertEquals([new Reference(UnitFormatter::class), new Reference(ChannelConversions::class), new Reference(UnitPreferences::class)], $container->getDefinition(MeasurementExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(MeasurementExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(MeasurementExtension::class)->hasTag('twig.runtime'));
        self::assertTrue($container->getParameter('phil_measurement.doctrine_type'));
    }

    public function testUnitsAndChannelsAreConfigured(): void
    {
        $container = $this->load([[
            'units' => [
                'mass' => ['units' => ['[gr]' => ['factor' => '0.00006479891', 'cldr' => 'mass-grain']]],
                'paper-weight' => ['standard' => 'g/m2', 'units' => ['g/m2' => ['factor' => '1'], 'kmpg' => ['factor' => '2', 'reciprocal' => true]]],
            ],
            'channels' => ['market_place' => ['mass' => '[gr]', 'paper-weight' => 'g/m2']],
            'doctrine_type' => false,
        ]]);

        self::assertSame([[
            'mass' => ['units' => ['[gr]' => ['factor' => '0.00006479891', 'cldr' => 'mass-grain']]],
            'paper-weight' => ['standard' => 'g/m2', 'units' => ['g/m2' => ['factor' => '1'], 'kmpg' => ['factor' => '2', 'reciprocal' => true]]],
        ]], $container->getDefinition(UnitRegistry::class)->getArguments());
        self::assertSame(['market_place' => ['mass' => '[gr]', 'paper-weight' => 'g/m2']], $container->getDefinition(ChannelConversions::class)->getArgument(1));
        self::assertFalse($container->getParameter('phil_measurement.doctrine_type'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedConfigurations(): iterable
    {
        yield 'unit defined twice' => [['units' => ['mass' => ['units' => ['g' => ['factor' => '1']]]]], 'phil_measurement: The unit "g" is already defined.'];
        yield 'new family without its standard unit' => [['units' => ['paper' => ['units' => ['a' => ['factor' => '1']]]]], 'phil_measurement: The family "paper" names its standard unit'];
        yield 'channel unit of another family' => [['channels' => ['shop' => ['mass' => 'm']]], 'phil_measurement: The unit "m" of the channel "shop" is not a unit of the family "mass".'];
        yield 'unknown channel unit' => [['channels' => ['shop' => ['mass' => 'stone']]], 'phil_measurement: The unit "stone" is not in the registry.'];
        yield 'family without units' => [['units' => ['mass' => ['standard' => 'kg']]], 'units'];
        yield 'unit without a factor' => [['units' => ['mass' => ['units' => ['[gr]' => ['cldr' => 'mass-grain']]]]], 'factor'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('refusedConfigurations')]
    public function testAConfigurationIsChecked(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains($message);
        $this->load([$config]);
    }

    public function testTheLibrarysRefusalIsKeptAsThePrevious(): void
    {
        try {
            $this->load([['channels' => ['shop' => ['mass' => 'stone']]]]);
            self::fail('An unknown unit is refused.');
        } catch (InvalidConfigurationException $invalidConfigurationException) {
            self::assertInstanceOf(MeasurementException::class, $invalidConfigurationException->getPrevious());
            self::assertSame(0, $invalidConfigurationException->getCode());
        }
    }

    public function testBootRegistersTheColumnTypeWithTheConfiguredRegistry(): void
    {
        $registry = UnitRegistry::standard()->withDefinitions(['mass' => ['units' => ['[gr]' => ['factor' => '0.00006479891']]]]);
        $bundle = new PhilMeasurementBundle();
        $container = new Container();
        $container->setParameter('phil_measurement.doctrine_type', true);
        $container->set(PhilMeasurementBundle::REGISTRY, $registry);

        $bundle->setContainer($container);

        // Booted twice, as a kernel rebooted in the same process: the second registry replaces the first.
        $bundle->boot();
        $bundle->boot();

        $type = $this->types()->get(MeasurementColumnType::NAME);
        self::assertInstanceOf(MeasurementColumnType::class, $type);
        self::assertSame('1 [gr]', $type->convertToPHPValue('1 [gr]', new SQLitePlatform())?->toString());
    }

    public function testBootLeavesTheColumnTypeAloneWhenItIsTurnedOff(): void
    {
        if (!$this->types()->has(MeasurementColumnType::NAME)) {
            $this->types()->register(MeasurementColumnType::NAME, new MeasurementColumnType(UnitRegistry::standard()));
        }

        $before = $this->types()->get(MeasurementColumnType::NAME);

        $bundle = new PhilMeasurementBundle();
        $container = new Container();
        $container->setParameter('phil_measurement.doctrine_type', false);
        $container->set(PhilMeasurementBundle::REGISTRY, UnitRegistry::standard());

        $bundle->setContainer($container);
        $bundle->boot();
        new PhilMeasurementBundle()->boot();

        self::assertSame($before, $this->types()->get(MeasurementColumnType::NAME));
    }

    private function types(): TypeRegistry
    {
        $types = new Configuration()->getTypeProvider();
        self::assertInstanceOf(TypeRegistry::class, $types);

        return $types;
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
        $extension = new PhilMeasurementBundle()->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        return $extension;
    }
}
