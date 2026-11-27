<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\Compiler\DoctrineOrmMappingsPass;
use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\Money\Money;
use Phil\MoneyBundle\Application\AcceptedCurrencies;
use Phil\MoneyBundle\Infrastructure\Doctrine\CurrencyType;
use Phil\MoneyBundle\Infrastructure\Doctrine\MoneyMappingDriver;
use Phil\MoneyBundle\Infrastructure\Form\MoneyType;
use Phil\MoneyBundle\Infrastructure\Twig\MoneyExtension;
use Phil\MoneyBundle\Infrastructure\Validator\AcceptedCurrencyValidator;
use Phil\MoneyBundle\Infrastructure\Validator\MoneyRangeValidator;
use Phil\MoneyBundle\PhilMoneyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilMoneyBundle::class)]
final class PhilMoneyBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilMoney(): void
    {
        self::assertSame('phil_money', $this->extension()->getAlias());
    }

    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([['currencies' => ['CAD', 'USD'], 'default_currency' => 'USD']]);

        self::assertSame([
            'service_container',
            AcceptedCurrencies::class,
            MoneyType::class,
            AcceptedCurrencyValidator::class,
            MoneyRangeValidator::class,
            MoneyExtension::class,
        ], array_keys($container->getDefinitions()));

        $accepted = $container->getDefinition(AcceptedCurrencies::class);
        self::assertSame([AcceptedCurrencies::class, 'of'], $accepted->getFactory());
        self::assertSame([['CAD', 'USD'], 'USD'], $accepted->getArguments());

        self::assertEquals([new Reference(AcceptedCurrencies::class)], $container->getDefinition(MoneyType::class)->getArguments());
        self::assertTrue($container->getDefinition(MoneyType::class)->hasTag('form.type'));
        self::assertEquals([new Reference(AcceptedCurrencies::class)], $container->getDefinition(AcceptedCurrencyValidator::class)->getArguments());
        self::assertTrue($container->getDefinition(AcceptedCurrencyValidator::class)->hasTag('validator.constraint_validator'));
        self::assertTrue($container->getDefinition(MoneyRangeValidator::class)->hasTag('validator.constraint_validator'));
        self::assertTrue($container->getDefinition(MoneyExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(MoneyExtension::class)->hasTag('twig.runtime'));
    }

    public function testEveryCurrencyIsAcceptedByDefault(): void
    {
        self::assertSame([[], null], $this->load([])->getDefinition(AcceptedCurrencies::class)->getArguments());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedConfigurations(): iterable
    {
        yield 'an unknown currency' => [['currencies' => ['CAD', 'XYZ']], '"XYZ" is not a known ISO 4217 currency code.'];
        yield 'an unknown default' => [['default_currency' => 'XYZ'], '"XYZ" is not a known ISO 4217 currency code.'];
        yield 'a default outside the list' => [['currencies' => ['CAD'], 'default_currency' => 'USD'], 'The default currency is not one of the accepted currencies'];
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

    public function testAnyDefaultIsAcceptedWithAnOpenList(): void
    {
        self::assertSame([[], 'EUR'], $this->load([['default_currency' => 'EUR']])->getDefinition(AcceptedCurrencies::class)->getArguments());
    }

    public function testTheColumnTypeIsPrependedAndTheEmbeddableTurnedOnBesideTheOrm(): void
    {
        $container = $this->prepend([], [['orm' => []]]);

        self::assertSame([
            ['orm' => []],
            ['dbal' => ['types' => [CurrencyType::NAME => CurrencyType::class]]],
        ], $container->getExtensionConfig('doctrine'));
        self::assertTrue($container->getParameter(PhilMoneyBundle::DOCTRINE_MAPPING));
    }

    public function testWithoutTheOrmOnlyTheColumnTypeIsPrepended(): void
    {
        $container = $this->prepend([], [['dbal' => []]]);

        self::assertSame([
            ['dbal' => []],
            ['dbal' => ['types' => [CurrencyType::NAME => CurrencyType::class]]],
        ], $container->getExtensionConfig('doctrine'));
        self::assertFalse($container->hasParameter(PhilMoneyBundle::DOCTRINE_MAPPING));
    }

    public function testTheEmbeddableCanBeLeftToTheApplication(): void
    {
        $container = $this->prepend([['doctrine_mapping' => false]], [['orm' => []]]);

        self::assertSame([
            ['orm' => []],
            ['dbal' => ['types' => [CurrencyType::NAME => CurrencyType::class]]],
        ], $container->getExtensionConfig('doctrine'));
        self::assertFalse($container->hasParameter(PhilMoneyBundle::DOCTRINE_MAPPING));
    }

    public function testTheDriverJoinsTheChainOfTheDefaultEntityManagerForMoneyAlone(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.default_entity_manager', 'default');
        $container->register('doctrine.orm.default_metadata_driver');
        $container->setParameter(PhilMoneyBundle::DOCTRINE_MAPPING, true);
        new PhilMoneyBundle()->build($container);
        $this->runMappingPasses($container);

        $calls = $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertIsArray($calls[0]);
        [$method, $arguments] = $calls[0];
        self::assertSame('addDriver', $method);
        self::assertIsArray($arguments);
        [$driver, $namespace] = $arguments;
        self::assertSame(Money::class, $namespace);
        self::assertInstanceOf(Definition::class, $driver);
        self::assertSame(MoneyMappingDriver::class, $driver->getClass());
    }

    public function testTheDriverStaysOutWhileTheMappingIsOff(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.default_entity_manager', 'default');
        $container->register('doctrine.orm.default_metadata_driver');
        new PhilMoneyBundle()->build($container);
        $this->runMappingPasses($container);

        self::assertSame([], $container->getDefinition('doctrine.orm.default_metadata_driver')->getMethodCalls());
    }

    public function testWithoutDoctrineNothingIsPrepended(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);
        // A configuration the bundle refuses is not even read.
        $container->prependExtensionConfig('phil_money', ['unknown' => true]);

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['unknown' => true]], $container->getExtensionConfig('phil_money'));
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
            $container->loadFromExtension('phil_money', $config);
        }

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilMoneyBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
