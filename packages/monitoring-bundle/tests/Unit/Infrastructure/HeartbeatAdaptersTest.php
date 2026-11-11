<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\MonitoringBundle\Application\Contract\HeartbeatTask;
use Phil\MonitoringBundle\Application\DeadManSwitch;
use Phil\MonitoringBundle\Application\Heartbeats;
use Phil\MonitoringBundle\Domain\ValueObject\ExpectedTask;
use Phil\MonitoringBundle\Infrastructure\Messenger\WatchHeartbeatsHandler;
use Phil\MonitoringBundle\Infrastructure\Scheduler\HeartbeatOnScheduledRun;
use Phil\MonitoringBundle\Tests\Unit\Support\InMemoryHeartbeats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use stdClass;
use Stringable;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Scheduler\Event\PostRunEvent;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\Trigger\CallbackTrigger;

#[CoversClass(HeartbeatOnScheduledRun::class)]
#[CoversClass(WatchHeartbeatsHandler::class)]
final class HeartbeatAdaptersTest extends TestCase
{
    private MockClock $clock;

    private InMemoryHeartbeats $beats;

    private Heartbeats $heartbeats;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
        $this->beats = new InMemoryHeartbeats();
        $this->heartbeats = new Heartbeats([new ExpectedTask('backup', 60)], $this->beats, $this->clock);
    }

    public function testAScheduledMessageBeatsItsTask(): void
    {
        $logger = $this->logger();
        new HeartbeatOnScheduledRun($this->heartbeats, $logger)($this->event($this->task('backup')));

        self::assertEquals(['backup' => new DateTimeImmutable('2026-11-08 10:00:00 UTC')], $this->beats->beats);
        self::assertSame([], $logger->logs);
    }

    public function testAnotherMessageBeatsNothing(): void
    {
        $logger = $this->logger();
        new HeartbeatOnScheduledRun($this->heartbeats, $logger)($this->event(new stdClass()));

        self::assertSame([], $this->beats->beats);
        self::assertSame([], $logger->logs);
    }

    public function testAnUndeclaredTaskIsLogged(): void
    {
        $logger = $this->logger();
        new HeartbeatOnScheduledRun($this->heartbeats, $logger)($this->event($this->task('unknown')));

        self::assertSame([], $this->beats->beats);
        self::assertSame([['warning', 'The scheduled task {task} beat, but it is not declared under phil_monitoring.heartbeats.tasks.', ['task' => 'unknown']]], $logger->logs);
    }

    public function testAnUndeclaredTaskWithoutALogger(): void
    {
        new HeartbeatOnScheduledRun($this->heartbeats)($this->event($this->task('unknown')));

        self::assertSame([], $this->beats->beats);
    }

    public function testTheWatchMessageRunsTheSwitch(): void
    {
        $this->heartbeats->beat('backup');
        $this->clock->modify('+2 minutes');

        new WatchHeartbeatsHandler(new DeadManSwitch($this->heartbeats, $this->beats, new LockFactory(new InMemoryStore())))();

        self::assertTrue($this->beats->isAlerted('backup'));
    }

    private function task(string $name): HeartbeatTask
    {
        return new readonly class($name) implements HeartbeatTask {
            public function __construct(
                private string $name,
            ) {
            }

            #[Override]
            public function heartbeatTask(): string
            {
                return $this->name;
            }
        };
    }

    private function event(object $message): PostRunEvent
    {
        $context = new MessageContext('default', 'id', new CallbackTrigger(static fn (): ?DateTimeImmutable => null), new DateTimeImmutable());

        return new PostRunEvent(new Schedule(), $context, $message);
    }

    /**
     * @return AbstractLogger&object{logs: list<array{mixed, string, array<array-key, mixed>}>}
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /**
             * @var list<array{mixed, string, array<array-key, mixed>}>
             */
            public array $logs = [];

            #[Override]
            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [$level, (string) $message, $context];
            }
        };
    }
}
