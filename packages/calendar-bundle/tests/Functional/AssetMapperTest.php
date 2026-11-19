<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Tests\Functional;

use Override;
use Phil\CalendarBundle\PhilCalendarBundle;
use Phil\CalendarBundle\Tests\Functional\App\AssetsKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The controller and the stylesheet served by AssetMapper when its bundle is registered; the kernel of the other
 * functional tests boots without it.
 */
#[CoversClass(PhilCalendarBundle::class)]
final class AssetMapperTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return AssetsKernel::class;
    }

    public function testTheControllerAndTheStylesheetAreServed(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel();
        $mapper = self::getContainer()->get('asset_mapper');
        self::assertInstanceOf(AssetMapperInterface::class, $mapper);

        self::assertNotNull($mapper->getAsset('@phil/calendar-bundle/calendar_controller.js'));
        $stylesheet = $mapper->getAsset('phil-calendar/calendar.css');
        self::assertNotNull($stylesheet);
        self::assertStringStartsWith('/assets/phil-calendar/calendar-', $stylesheet->publicPath);
    }
}
