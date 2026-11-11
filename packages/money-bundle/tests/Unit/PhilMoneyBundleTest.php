<?php

declare(strict_types=1);

namespace Phil\MoneyBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\MoneyBundle\Application\AcceptedCurrencies;
use Phil\MoneyBundle\Infrastructure\Doctrine\CurrencyType;
use Phil\MoneyBundle\Infrastructure\Form\MoneyType;
use Phil\MoneyBundle\Infrastructure\Twig\MoneyExtension;
use Phil\MoneyBundle\Infrastructure\Validator\AcceptedCurrencyValidator;
use Phil\MoneyBundle\Infrastructure\Validator\MoneyRangeValidator;
use Phil\MoneyBundle\PhilMoneyBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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

    public function testTheColumnTypeAndTheEmbeddableArePrependedToDoctrine(): void
    {
        $container = $this->prepend([], [['orm' => []]]);
        $bundle = new PhilMoneyBundle();

        self::assertSame([
            ['orm' => []],
            ['dbal' => ['types' => [CurrencyType::NAME => CurrencyType::class]]],
            ['orm' => ['mappings' => ['PhilMoney' => [
                'type' => 'xml',
                'is_bundle' => false,
                'dir' => $bundle->getPath().'/config/doctrine',
                'prefix' => 'Phil\Money',
            ]]]],
        ], $container->getExtensionConfig('doctrine'));
        self::assertFileExists($bundle->getPath().'/config/doctrine/Money.orm.xml');
    }

    public function testWithoutTheOrmOnlyTheColumnTypeIsPrepended(): void
    {
        self::assertSame([
            ['dbal' => []],
            ['dbal' => ['types' => [CurrencyType::NAME => CurrencyType::class]]],
        ], $this->prepend([], [['dbal' => []]])->getExtensionConfig('doctrine'));
    }

    public function testTheEmbeddableCanBeLeftToTheApplication(): void
    {
        self::assertSame([
            ['orm' => []],
            ['dbal' => ['types' => [CurrencyType::NAME => CurrencyType::class]]],
        ], $this->prepend([['doctrine_mapping' => false]], [['orm' => []]])->getExtensionConfig('doctrine'));
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
