<?php

declare(strict_types=1);

namespace Phil\AddressBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\AddressBundle\Infrastructure\Doctrine\AddressColumnType;
use Phil\AddressBundle\Infrastructure\Form\AddressType;
use Phil\AddressBundle\Infrastructure\Http\AddressFormatController;
use Phil\AddressBundle\Infrastructure\Twig\AddressExtension;
use Phil\AddressBundle\Infrastructure\Validator\ValidAddressValidator;
use Phil\AddressBundle\PhilAddressBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilAddressBundle::class)]
final class PhilAddressBundleTest extends TestCase
{
    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([['default_country' => 'CA', 'format_url' => '/address-format/__COUNTRY__']]);
        $translator = new Reference('translator', ContainerInterface::NULL_ON_INVALID_REFERENCE);

        self::assertSame('phil_address', $this->extension()->getAlias());
        self::assertSame(['service_container', AddressType::class, ValidAddressValidator::class, AddressFormatController::class, AddressExtension::class], array_keys($container->getDefinitions()));
        self::assertEquals(['CA', '/address-format/__COUNTRY__', $translator], $container->getDefinition(AddressType::class)->getArguments());
        self::assertTrue($container->getDefinition(AddressType::class)->hasTag('form.type'));
        self::assertTrue($container->getDefinition(ValidAddressValidator::class)->hasTag('validator.constraint_validator'));
        self::assertEquals([$translator], $container->getDefinition(AddressFormatController::class)->getArguments());
        self::assertTrue($container->getDefinition(AddressFormatController::class)->hasTag('controller.service_arguments'));
        self::assertTrue($container->getDefinition(AddressFormatController::class)->isPublic());
        self::assertTrue($container->getDefinition(AddressExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(AddressExtension::class)->hasTag('twig.runtime'));
        self::assertEquals([null, null, $translator], $this->load([])->getDefinition(AddressType::class)->getArguments());
    }

    public function testTheDefaultCountryIsACountry(): void
    {
        foreach (['ca', 'ZZ', 12] as $country) {
            try {
                $this->load([['default_country' => $country]]);
                self::fail('A bad default country was taken.');
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('The default country is the ISO 3166-1 alpha-2 code of a country, in capitals', $invalidConfigurationException->getMessage());
            }
        }
    }

    public function testTheColumnTypeIsPrependedToDoctrine(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new DoctrineExtension());

        $extension = $this->extension();
        $container->registerExtension($extension);
        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        self::assertSame([['dbal' => ['types' => [AddressColumnType::NAME => AddressColumnType::class]]]], $container->getExtensionConfig('doctrine'));

        $bare = new ContainerBuilder();
        $bare->registerExtension($extension);

        $extension->prepend($bare);
        self::assertFalse($bare->hasExtension('doctrine'));
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
        $extension = new PhilAddressBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
