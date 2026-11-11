<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Tests\Unit;

use Doctrine\Bundle\DoctrineBundle\DependencyInjection\DoctrineExtension;
use Phil\UrlBundle\Infrastructure\Doctrine\UrlColumnType;
use Phil\UrlBundle\Infrastructure\Form\TypedUrlType;
use Phil\UrlBundle\Infrastructure\Twig\UrlExtension;
use Phil\UrlBundle\Infrastructure\Validator\UrlOfTypeValidator;
use Phil\UrlBundle\PhilUrlBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;

#[CoversClass(PhilUrlBundle::class)]
final class PhilUrlBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilUrl(): void
    {
        self::assertSame('phil_url', $this->extension()->getAlias());
    }

    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([]);

        self::assertSame(['service_container', TypedUrlType::class, UrlOfTypeValidator::class, UrlExtension::class], array_keys($container->getDefinitions()));
        self::assertTrue($container->getDefinition(TypedUrlType::class)->hasTag('form.type'));
        self::assertTrue($container->getDefinition(UrlOfTypeValidator::class)->hasTag('validator.constraint_validator'));
        self::assertTrue($container->getDefinition(UrlExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(UrlExtension::class)->hasTag('twig.runtime'));
    }

    public function testTheColumnTypeIsPrependedToDoctrine(): void
    {
        self::assertSame([
            ['dbal' => []],
            ['dbal' => ['types' => [UrlColumnType::NAME => UrlColumnType::class]]],
        ], $this->prepend([], [['dbal' => []]])->getExtensionConfig('doctrine'));
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
            $container->loadFromExtension('phil_url', $config);
        }

        self::assertInstanceOf(PrependExtensionInterface::class, $extension);
        $extension->prepend($container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilUrlBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
