<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Tests\Unit;

use Phil\InstantMessengerBundle\Infrastructure\Form\HandleType;
use Phil\InstantMessengerBundle\Infrastructure\Twig\MessengerExtension;
use Phil\InstantMessengerBundle\Infrastructure\Validator\HandleOfTypeValidator;
use Phil\InstantMessengerBundle\PhilInstantMessengerBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

#[CoversClass(PhilInstantMessengerBundle::class)]
final class PhilInstantMessengerBundleTest extends TestCase
{
    public function testTheExtensionAliasIsPhilInstantMessenger(): void
    {
        self::assertSame('phil_instant_messenger', $this->extension()->getAlias());
    }

    public function testTheServicesAreRegistered(): void
    {
        $container = $this->load([]);

        self::assertSame(['service_container', HandleType::class, HandleOfTypeValidator::class, MessengerExtension::class], array_keys($container->getDefinitions()));
        self::assertTrue($container->getDefinition(HandleType::class)->hasTag('form.type'));
        self::assertTrue($container->getDefinition(HandleOfTypeValidator::class)->hasTag('validator.constraint_validator'));
        self::assertTrue($container->getDefinition(MessengerExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(MessengerExtension::class)->hasTag('twig.runtime'));
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
        $extension = new PhilInstantMessengerBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
