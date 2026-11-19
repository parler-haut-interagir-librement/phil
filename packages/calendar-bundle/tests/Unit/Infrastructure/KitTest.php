<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Unit\Infrastructure;

use Phil\CalendarBundle\Application\Calendar;
use Phil\CalendarBundle\Infrastructure\DependencyInjection\KitSubdirectoryPass;
use Phil\CalendarBundle\Infrastructure\Twig\CalendarExtension;
use Phil\CalendarBundle\Infrastructure\Twig\KitComponents;
use Phil\CalendarBundle\Tests\Unit\Fixtures\InMemoryEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(KitComponents::class)]
#[CoversClass(KitSubdirectoryPass::class)]
#[CoversClass(CalendarExtension::class)]
final class KitTest extends TestCase
{
    public function testARecipeIsNamedAfterItsSubdirectory(): void
    {
        self::assertSame('Popover:Trigger', new KitComponents()->name('Popover:Trigger'));
        self::assertSame('ui:Badge', new KitComponents('/ui/')->name('Badge'));
        self::assertSame('ui:kit:Card:Title', new CalendarExtension(new Calendar(new InMemoryEvents()), kit: new KitComponents('ui/kit'))->kit('Card:Title'));
        self::assertSame('Button', new CalendarExtension(new Calendar(new InMemoryEvents()))->kit('Button'));
    }

    public function testTheSubdirectoryOfTheToolkitIsReadUnlessTheConfigurationSaysIt(): void
    {
        $container = new ContainerBuilder();
        $container->register(KitComponents::class)->setArguments([null]);
        $container->setParameter('.ux_toolkit.components.subdirectory', 'ui');
        new KitSubdirectoryPass()->process($container);
        self::assertSame(['ui'], $container->getDefinition(KitComponents::class)->getArguments());

        $container->getDefinition(KitComponents::class)->setArguments(['mine']);
        new KitSubdirectoryPass()->process($container);
        self::assertSame(['mine'], $container->getDefinition(KitComponents::class)->getArguments());

        $alone = new ContainerBuilder();
        $alone->register(KitComponents::class)->setArguments([null]);
        new KitSubdirectoryPass()->process($alone);
        self::assertSame([''], $alone->getDefinition(KitComponents::class)->getArguments());

        $nothing = new ContainerBuilder();
        new KitSubdirectoryPass()->process($nothing);
        self::assertFalse($nothing->hasDefinition(KitComponents::class));

        $null = new ContainerBuilder();
        $null->register(KitComponents::class)->setArguments([null]);
        $null->setParameter('.ux_toolkit.components.subdirectory', null);
        new KitSubdirectoryPass()->process($null);
        self::assertSame([''], $null->getDefinition(KitComponents::class)->getArguments());
    }
}
