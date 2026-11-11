<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\PhoneBundle\Infrastructure\Doctrine\PhoneNumberColumnType;
use Phil\PhoneBundle\Infrastructure\Form\PhoneType;
use Phil\PhoneBundle\Infrastructure\Twig\PhoneExtension;
use Phil\PhoneBundle\Infrastructure\Validator\ValidPhoneNumberValidator;
use Phil\PhoneBundle\PhilPhoneBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

#[CoversClass(PhilPhoneBundle::class)]
final class PhilPhoneBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilPhone(): void
    {
        self::assertSame('phil_phone', $this->extension()->getAlias());
    }

    public function testTheServicesAreRegisteredWithoutDefaultRegion(): void
    {
        $container = $this->load([]);

        self::assertSame(['service_container', PhoneType::class, ValidPhoneNumberValidator::class, PhoneExtension::class], array_keys($container->getDefinitions()));
        self::assertTrue($container->getDefinition(PhoneType::class)->hasTag('form.type'));
        self::assertTrue($container->getDefinition(ValidPhoneNumberValidator::class)->hasTag('validator.constraint_validator'));
        self::assertTrue($container->getDefinition(PhoneExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(PhoneExtension::class)->hasTag('twig.runtime'));

        foreach ([PhoneType::class, ValidPhoneNumberValidator::class, PhoneExtension::class] as $service) {
            self::assertSame([null], $container->getDefinition($service)->getArguments());
        }
    }

    public function testTheDefaultRegionReachesEveryService(): void
    {
        $container = $this->load([['default_region' => 'CA']]);

        foreach ([PhoneType::class, ValidPhoneNumberValidator::class, PhoneExtension::class] as $service) {
            self::assertSame(['CA'], $container->getDefinition($service)->getArguments());
        }
    }

    public function testTheDefaultRegionIsACountryWithAPlan(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The default region is the ISO 3166-1 alpha-2 code of a country with a numbering plan, in capitals: "ca".');

        $this->load([['default_region' => 'ca']]);
    }

    public function testTheColumnTypeIsPrependedToDoctrine(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());
        $container->loadFromExtension('doctrine', ['dbal' => []]);

        $extension = $this->extension();
        $container->registerExtension($extension);

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([
            ['dbal' => []],
            ['dbal' => ['types' => [PhoneNumberColumnType::NAME => PhoneNumberColumnType::class]]],
        ], $container->getExtensionConfig('doctrine'));
    }

    public function testWithoutDoctrineNothingIsPrepended(): void
    {
        $container = new ContainerBuilder();
        $extension = $this->extension();
        $container->registerExtension($extension);

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertFalse($container->hasExtension('doctrine'));
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
        $extension = new PhilPhoneBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
