<?php

declare(strict_types=1);

namespace Phil\MenuBundle\Tests\Unit;

use Phil\MenuBundle\Application\MenuBuilder;
use Phil\MenuBundle\Application\MenuDecorator;
use Phil\MenuBundle\Application\Menus;
use Phil\MenuBundle\Infrastructure\Twig\MenuExtension;
use Phil\MenuBundle\PhilMenuBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilMenuBundle::class)]
final class PhilMenuBundleTest extends TestCase
{
    public function testTheServicesAndTheAutoconfigurationAreRegistered(): void
    {
        $extension = new PhilMenuBundle()->getContainerExtension();
        self::assertNotNull($extension);
        self::assertSame('phil_menu', $extension->getAlias());

        $container = new ContainerBuilder();
        $extension->load([], $container);

        self::assertSame(['service_container', Menus::class, MenuExtension::class], array_keys($container->getDefinitions()));
        $menus = $container->getDefinition(Menus::class);
        self::assertTrue($menus->isPublic());
        self::assertEquals([new TaggedIteratorArgument('phil_menu.builder'), new TaggedIteratorArgument('phil_menu.decorator')], $menus->getArguments());
        self::assertEquals([new Reference(Menus::class), new Reference('request_stack'), new Reference('translator', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), new Reference('router', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)], $container->getDefinition(MenuExtension::class)->getArguments());
        self::assertTrue($container->getDefinition(MenuExtension::class)->hasTag('twig.attribute_extension'));
        self::assertTrue($container->getDefinition(MenuExtension::class)->hasTag('twig.runtime'));

        $autoconfigured = $container->getAutoconfiguredInstanceof();
        self::assertSame(['phil_menu.builder' => [[]]], $autoconfigured[MenuBuilder::class]->getTags());
        self::assertSame(['phil_menu.decorator' => [[]]], $autoconfigured[MenuDecorator::class]->getTags());
        self::assertSame(['phil_menu.builder', 'phil_menu.decorator'], [PhilMenuBundle::BUILDER_TAG, PhilMenuBundle::DECORATOR_TAG]);
        self::assertFalse($container->getParameter('phil_menu.allow_localhost'));
    }

    public function testLocalhostIsAllowedByTheConfiguration(): void
    {
        $extension = new PhilMenuBundle()->getContainerExtension();
        self::assertNotNull($extension);
        $container = new ContainerBuilder();
        $extension->load([['allow_localhost' => true]], $container);

        self::assertTrue($container->getParameter('phil_menu.allow_localhost'));
    }
}
