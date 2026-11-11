<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\MonitoringBundle\Application\DeadManSwitch;
use Phil\MonitoringBundle\Application\HealthChecker;
use Phil\MonitoringBundle\Application\Heartbeats;
use Phil\MonitoringBundle\Application\Maintenance;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;
use Phil\MonitoringBundle\Domain\ValueObject\ExpectedTask;
use Phil\MonitoringBundle\Domain\ValueObject\MaintenanceState;
use Phil\MonitoringBundle\Infrastructure\Console\HealthCommand;
use Phil\MonitoringBundle\Infrastructure\Console\HeartbeatCommand;
use Phil\MonitoringBundle\Infrastructure\Console\MaintenanceCommand;
use Phil\MonitoringBundle\Infrastructure\Console\WatchCommand;
use Phil\MonitoringBundle\Tests\Unit\Support\InMemoryHeartbeats;
use Phil\MonitoringBundle\Tests\Unit\Support\InMemoryMaintenance;
use Phil\MonitoringBundle\Tests\Unit\Support\StubCheck;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

#[CoversClass(HealthCommand::class)]
#[CoversClass(HeartbeatCommand::class)]
#[CoversClass(WatchCommand::class)]
#[CoversClass(MaintenanceCommand::class)]
final class CommandsTest extends TestCase
{
    private MockClock $clock;

    private InMemoryHeartbeats $beats;

    private Heartbeats $heartbeats;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
        $this->beats = new InMemoryHeartbeats();
        $this->heartbeats = new Heartbeats([new ExpectedTask('backup', 60), new ExpectedTask('report', 60)], $this->beats, $this->clock);
    }

    public function testTheHealthOfEveryCheck(): void
    {
        $tester = new CommandTester(new HealthCommand(new HealthChecker([StubCheck::answering('database', CheckOutcome::up()), StubCheck::answering('cache.app', CheckOutcome::degraded('slow'))], $this->clock)));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("database: up, 0 ms\ncache.app: degraded, 0 ms — slow\nOverall: degraded\n", $tester->getDisplay());
        self::assertSame('phil:monitoring:health', new HealthCommand(new HealthChecker([], $this->clock))->getName());
    }

    public function testTheHealthFailsWhileACheckIsDown(): void
    {
        $tester = new CommandTester(new HealthCommand(new HealthChecker([StubCheck::answering('database', CheckOutcome::down('gone'))], $this->clock)));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringEndsWith("Overall: down\n", $tester->getDisplay());
    }

    public function testATaskBeats(): void
    {
        $tester = new CommandTester(new HeartbeatCommand($this->heartbeats));

        self::assertSame(Command::SUCCESS, $tester->execute(['task' => 'backup']));
        self::assertSame('', $tester->getDisplay());
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:00:00 UTC'), $this->beats->lastBeat('backup'));
    }

    public function testAnUndeclaredTaskFails(): void
    {
        $tester = new CommandTester(new HeartbeatCommand($this->heartbeats));

        self::assertSame(Command::FAILURE, $tester->execute(['task' => 'unknown']));
        self::assertSame("The task \"unknown\" is not declared under phil_monitoring.heartbeats.tasks.\n", $tester->getDisplay());
        self::assertSame([], $this->beats->beats);
    }

    public function testTheWatchAlertsAndFailsWhileATaskIsLate(): void
    {
        self::assertSame([Command::SUCCESS, "backup: never\nreport: never\n"], $this->watch());

        $this->heartbeats->beat('backup');
        $this->clock->modify('+30 seconds');
        $this->heartbeats->beat('report');
        $this->clock->modify('+31 seconds');

        self::assertSame([Command::FAILURE, "backup: late, last run 2026-11-08T10:00:00+00:00, alerted\nreport: on_time, last run 2026-11-08T10:00:30+00:00\n"], $this->watch());
        self::assertSame([Command::FAILURE, "backup: late, last run 2026-11-08T10:00:00+00:00\nreport: on_time, last run 2026-11-08T10:00:30+00:00\n"], $this->watch());
    }

    public function testTheWatchFailsWhenTheLateTaskIsNotTheLast(): void
    {
        $this->heartbeats->beat('backup');
        $this->clock->modify('+2 minutes');
        $this->heartbeats->beat('report');

        self::assertSame(Command::FAILURE, $this->watch()[0]);
    }

    public function testTheWatchFailsWhileEveryTaskIsLate(): void
    {
        $this->heartbeats->beat('backup');
        $this->heartbeats->beat('report');

        $this->clock->modify('+2 minutes');

        self::assertSame(Command::FAILURE, $this->watch()[0]);
    }

    public function testTheMaintenanceIsEnabledShownAndDisabled(): void
    {
        $store = new InMemoryMaintenance();
        $tester = new CommandTester(new MaintenanceCommand(new Maintenance($store, $this->clock, 300)));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("The site is open.\n", $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['action' => 'enable']));
        self::assertSame("The site is in maintenance; visitors are told to come back in 300 seconds.\n", $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['action' => 'enable', '--retry-after' => '0600']));
        self::assertSame("The site is in maintenance; visitors are told to come back in 600 seconds.\n", $tester->getDisplay());
        self::assertEquals(new MaintenanceState(new DateTimeImmutable('2026-11-08 10:00:00 UTC'), 600), $store->state);

        self::assertSame(Command::SUCCESS, $tester->execute(['action' => 'status']));
        self::assertSame("The site is in maintenance since 2026-11-08T10:00:00+00:00.\n", $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['action' => 'disable']));
        self::assertSame("The site is open.\n", $tester->getDisplay());
        self::assertNull($store->state);
    }

    public function testTheMaintenanceRefusesWhatItDoesNotKnow(): void
    {
        $store = new InMemoryMaintenance();
        $tester = new CommandTester(new MaintenanceCommand(new Maintenance($store, $this->clock, 300)));

        self::assertSame(Command::INVALID, $tester->execute(['action' => 'pause']));
        self::assertSame("The action is enable, disable or status.\n", $tester->getDisplay());

        foreach (['soon', '-1', '1.5', '1234567890', "60\n", ' 60'] as $retryAfter) {
            self::assertSame(Command::INVALID, $tester->execute(['action' => 'enable', '--retry-after' => $retryAfter]));
            self::assertSame("--retry-after is a number of seconds.\n", $tester->getDisplay());
        }

        self::assertSame(Command::SUCCESS, $tester->execute(['action' => 'enable', '--retry-after' => '123456789']));
        self::assertSame(123_456_789, $store->state?->retryAfterSeconds);
    }

    /**
     * @return array{int, string}
     */
    private function watch(): array
    {
        $tester = new CommandTester(new WatchCommand(new DeadManSwitch($this->heartbeats, $this->beats, new LockFactory(new InMemoryStore())), $this->heartbeats));
        $code = $tester->execute([]);

        return [$code, $tester->getDisplay()];
    }
}
