<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\MonitoringBundle\Application\HealthChecker;
use Phil\MonitoringBundle\Application\Heartbeats;
use Phil\MonitoringBundle\Application\Maintenance;
use Phil\MonitoringBundle\Application\Monitoring;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\MonitoringBundle\Domain\Status\HeartbeatState;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\MonitoringBundle\Domain\ValueObject\ExpectedTask;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Phil\MonitoringBundle\Tests\Unit\Support\InMemoryHeartbeats;
use Phil\MonitoringBundle\Tests\Unit\Support\InMemoryMaintenance;
use Phil\MonitoringBundle\Tests\Unit\Support\StubCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Maintenance::class)]
#[CoversClass(Monitoring::class)]
final class MaintenanceTest extends TestCase
{
    public function testTheMaintenanceIsEnabledWithTheConfiguredDelayOrItsOwn(): void
    {
        $store = new InMemoryMaintenance();
        $maintenance = new Maintenance($store, new MockClock('2026-11-08 10:00:00 UTC'), 300);
        $since = new DateTimeImmutable('2026-11-08 10:00:00 UTC');

        self::assertNull($maintenance->current());
        self::assertEquals(new MaintenanceState($since, 300), $maintenance->enable());
        self::assertEquals(new MaintenanceState($since, 300), $maintenance->current());
        self::assertEquals(new MaintenanceState($since, 60), $maintenance->enable(60));
        self::assertEquals(new MaintenanceState($since, 60), $store->state);

        $maintenance->disable();

        self::assertNull($store->state);
    }

    public function testTheReadModel(): void
    {
        $clock = new MockClock('2026-11-08 10:00:00 UTC');
        $maintenance = new Maintenance(new InMemoryMaintenance(), $clock, 300);
        $maintenance->enable();

        $monitoring = new Monitoring(
            new HealthChecker([StubCheck::answering('database', CheckOutcome::degraded('slow'))], $clock),
            new Heartbeats([new ExpectedTask('backup', 60)], new InMemoryHeartbeats(), $clock),
            $maintenance,
        );

        self::assertSame(HealthStatus::Degraded, $monitoring->health()->status());
        self::assertSame(HeartbeatState::Never, $monitoring->heartbeats()[0]->state);
        self::assertSame(300, $monitoring->maintenance()?->retryAfterSeconds);
    }
}
