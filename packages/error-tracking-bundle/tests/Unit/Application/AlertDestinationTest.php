<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Tests\Unit\Application;

use Phil\ErrorTrackingBundle\Application\AlertDestination;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AlertDestination::class)]
final class AlertDestinationTest extends TestCase
{
    public function testOnlyAlertsThatReachNoOneAreDegraded(): void
    {
        self::assertTrue(AlertDestination::None->isDegraded());
        self::assertFalse(AlertDestination::Notification->isDegraded());
        self::assertFalse(AlertDestination::Chat->isDegraded());
        self::assertFalse(AlertDestination::Disabled->isDegraded(), 'alerts turned off on purpose are not degraded');
    }

    public function testEachDestinationIsDescribed(): void
    {
        self::assertSame('The alerts are notified to the configured recipients or audience.', AlertDestination::Notification->describe());
        self::assertSame("The alerts are sent as chat messages through the notifier's chatter.", AlertDestination::Chat->describe());
        self::assertSame('The alerts are turned off.', AlertDestination::Disabled->describe());
        self::assertSame('No alert destination: name recipients or an audience, or configure a chatter transport.', AlertDestination::None->describe());
    }
}
