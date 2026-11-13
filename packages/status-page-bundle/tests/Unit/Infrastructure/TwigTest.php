<?php

declare(strict_types=1);

namespace Phil\StatusPageBundle\Tests\Unit\Infrastructure;

use Phil\StatusPageBundle\Domain\Status\ComponentStatus;
use Phil\StatusPageBundle\Infrastructure\Twig\KitComponents;
use Phil\StatusPageBundle\Infrastructure\Twig\StatusPageExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(KitComponents::class)]
#[CoversClass(StatusPageExtension::class)]
final class TwigTest extends TestCase
{
    public function testRecipesAreNamedAfterTheSubdirectory(): void
    {
        self::assertSame('Card:Title', new KitComponents()->name('Card:Title'));
        self::assertSame('Card', new KitComponents('/')->name('Card'));
        self::assertSame('ui:Card', new KitComponents('/ui/')->name('Card'));
        self::assertSame('ui:kit:Badge', new KitComponents('ui/kit')->name('Badge'));
        self::assertSame('ui:Badge', new StatusPageExtension(new KitComponents('ui'))->kit('Badge'));
        self::assertSame('Badge', new StatusPageExtension()->kit('Badge'));
    }

    public function testEachStateHasItsVariant(): void
    {
        $extension = new StatusPageExtension();

        self::assertSame('outline', $extension->variant(ComponentStatus::Operational));
        self::assertSame('secondary', $extension->variant(ComponentStatus::Maintenance));
        self::assertSame('secondary', $extension->variant(ComponentStatus::Degraded));
        self::assertSame('destructive', $extension->variant(ComponentStatus::PartialOutage));
        self::assertSame('destructive', $extension->variant(ComponentStatus::MajorOutage));
    }
}
