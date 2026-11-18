<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\MonitoringBundle\Application\Check\HeartbeatsCheck;
use Phil\MonitoringBundle\Application\DeadManSwitch;
use Phil\MonitoringBundle\Application\Heartbeats;
use Phil\MonitoringBundle\Domain\Contract\HeartbeatStore;
use Phil\MonitoringBundle\Domain\Status\HealthStatus;
use Phil\MonitoringBundle\Domain\Status\HeartbeatState;
use Phil\MonitoringBundle\Domain\ValueObject\ExpectedTask;
use Phil\MonitoringBundle\Domain\ValueObject\HeartbeatStatus;
use Phil\MonitoringBundle\Tests\Unit\Support\ExpiringLockStore;
use Phil\MonitoringBundle\Tests\Unit\Support\HookedHeartbeats;
use Phil\MonitoringBundle\Tests\Unit\Support\InMemoryHeartbeats;
use Phil\MonitoringBundle\Tests\Unit\Support\RecordingAdminNotifier;
use Phil\MonitoringBundle\Tests\Unit\Support\RecordingNotifier;
use Phil\MonitoringBundle\Tests\Unit\Support\ScriptedLockStore;
use Phil\MonitoringBundle\Tests\Unit\Support\SlowNotifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Lock\Exception\LockStorageException;
use Symfony\Component\Lock\Key;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\PersistingStoreInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Throwable;

#[CoversClass(Heartbeats::class)]
#[CoversClass(DeadManSwitch::class)]
#[CoversClass(HeartbeatsCheck::class)]
final class HeartbeatsTest extends TestCase
{
    private MockClock $clock;

    private InMemoryHeartbeats $store;

    private Heartbeats $heartbeats;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-08 10:00:00 UTC');
        $this->store = new InMemoryHeartbeats();
        $this->heartbeats = new Heartbeats([new ExpectedTask('backup', 3600), new ExpectedTask('report', 60, 30)], $this->store, $this->clock);
    }

    public function testOnlyADeclaredTaskBeats(): void
    {
        self::assertTrue($this->heartbeats->beat('backup'));
        self::assertFalse($this->heartbeats->beat('unknown'));
        self::assertEquals(['backup' => new DateTimeImmutable('2026-11-08 10:00:00 UTC')], $this->store->beats);
    }

    public function testEveryTaskStandsInTheOrderOfTheConfiguration(): void
    {
        $this->heartbeats->beat('report');
        $this->clock->modify('+91 seconds');

        $statuses = $this->heartbeats->statuses();

        self::assertSame(['backup', 'report'], array_map(static fn (HeartbeatStatus $status): string => $status->task, $statuses));
        self::assertSame([HeartbeatState::Never, HeartbeatState::Late], array_map(static fn (HeartbeatStatus $status): HeartbeatState => $status->state, $statuses));
        self::assertEquals(new DateTimeImmutable('2026-11-08 10:01:30 UTC'), $statuses[1]->dueBy);
    }

    public function testALateTaskIsAlertedOnceUntilItBeatsAgain(): void
    {
        $notifier = new RecordingNotifier();
        $switch = new DeadManSwitch($this->heartbeats, $this->store, new LockFactory(new InMemoryStore()), $notifier, ['chat/slack'], Notification::IMPORTANCE_HIGH);
        $this->heartbeats->beat('backup');
        $this->heartbeats->beat('report');

        self::assertSame([], $switch->watch());

        $this->clock->modify('+2 minutes');
        self::assertSame(['report'], $switch->watch());
        self::assertSame([], $switch->watch());
        self::assertSame(1, $notifier->count());

        [$notification, $recipients] = $notifier->sent[0];
        self::assertSame('Scheduled task "report" is late', $notification->getSubject());
        self::assertSame('It was expected to run again by 2026-11-08T10:01:30+00:00.', $notification->getContent());
        self::assertSame(Notification::IMPORTANCE_HIGH, $notification->getImportance());
        self::assertSame(['chat/slack'], $notification->getChannels(new Recipient('ops@example.com')));
        self::assertSame([], $recipients);

        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');
        self::assertSame(['report'], $switch->watch());
        self::assertSame(2, $notifier->count());
    }

    public function testTheAlertGoesToTheAdminRecipients(): void
    {
        $admin = new Recipient('ops@example.com');
        $notifier = new RecordingAdminNotifier([$admin]);
        $switch = new DeadManSwitch($this->heartbeats, $this->store, new LockFactory(new InMemoryStore()), $notifier, ['email']);
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        $switch->watch();

        self::assertSame([$admin], $notifier->sent[0][1]);
        self::assertSame(Notification::IMPORTANCE_URGENT, $notifier->sent[0][0]->getImportance());
    }

    public function testWithoutANotifierTheLateTasksAreMarkedAndReturned(): void
    {
        $switch = new DeadManSwitch($this->heartbeats, $this->store, new LockFactory(new InMemoryStore()));
        $this->heartbeats->beat('backup');
        $this->heartbeats->beat('report');

        $this->clock->modify('+2 hours');

        self::assertSame(['backup', 'report'], $switch->watch());
        self::assertTrue($this->store->isAlerted('report'));
        self::assertSame([], $switch->watch());
    }

    public function testAnAlertThatCannotBeSentIsLoggedAndTriedAgain(): void
    {
        $exception = new RuntimeException('No transport.');
        $notifier = new class($exception) implements NotifierInterface {
            public int $calls = 0;

            public function __construct(
                private readonly RuntimeException $exception,
            ) {
            }

            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                ++$this->calls;

                throw $this->exception;
            }
        };
        $logger = new class extends AbstractLogger {
            /**
             * @var list<array{mixed, string, array<array-key, mixed>}>
             */
            public array $logs = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [$level, (string) $message, $context];
            }
        };
        $this->heartbeats->beat('backup');
        $this->heartbeats->beat('report');

        $this->clock->modify('+2 hours');

        self::assertSame([], new DeadManSwitch($this->heartbeats, $this->store, new LockFactory(new InMemoryStore()), $notifier, logger: $logger)->watch());
        self::assertSame(2, $notifier->calls);
        self::assertFalse($this->store->isAlerted('backup'));
        self::assertSame([
            ['error', 'The lateness of the scheduled task {task} could not be alerted.', ['task' => 'backup', 'exception' => $exception]],
            ['error', 'The lateness of the scheduled task {task} could not be alerted.', ['task' => 'report', 'exception' => $exception]],
        ], $logger->logs);

        self::assertSame([], new DeadManSwitch($this->heartbeats, $this->store, new LockFactory(new InMemoryStore()), $notifier)->watch());
        self::assertSame(4, $notifier->calls);
    }

    public function testARoundThatFindsTheLockTakenSkipsTheTask(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $notifier = new RecordingNotifier();
        $other = new DeadManSwitch($this->heartbeats, $this->store, $locks, $notifier);
        $during = [];
        $concurrent = new class($notifier, $other, $locks, $during) implements NotifierInterface {
            /**
             * @param list<mixed> $during
             */
            public function __construct(
                private readonly RecordingNotifier $notifier,
                private readonly DeadManSwitch $other,
                private readonly LockFactory $locks,
                public array $during,
            ) {
            }

            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                $this->during[] = $this->other->watch();
                $this->during[] = $this->locks->createLock(DeadManSwitch::LOCK_PREFIX.'report')->acquire();
                $this->notifier->send($notification, ...$recipients);
            }
        };
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        self::assertSame(['report'], new DeadManSwitch($this->heartbeats, $this->store, $locks, $concurrent)->watch());
        self::assertSame([[], false], $concurrent->during);
        self::assertSame(1, $notifier->count());
        self::assertTrue($locks->createLock(DeadManSwitch::LOCK_PREFIX.'report')->acquire());
        self::assertSame([], $other->watch());
    }

    public function testTheRoundThatTakesTheLockReadsTheMarkAgain(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $notifier = new RecordingNotifier();
        $first = new DeadManSwitch($this->heartbeats, $this->store, $locks, $notifier);
        $interleaved = new class($this->store, $first) implements HeartbeatStore {
            private bool $raced = false;

            public function __construct(
                private readonly InMemoryHeartbeats $store,
                private readonly DeadManSwitch $first,
            ) {
            }

            public function lastBeat(string $task): ?DateTimeImmutable
            {
                return $this->store->lastBeat($task);
            }

            public function beat(string $task, DateTimeImmutable $at): void
            {
                $this->store->beat($task, $at);
            }

            public function isAlerted(string $task): bool
            {
                $alerted = $this->store->isAlerted($task);

                if (!$this->raced) {
                    $this->raced = true;
                    $this->first->watch();
                }

                return $alerted;
            }

            public function markAlerted(string $task): void
            {
                $this->store->markAlerted($task);
            }
        };
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        self::assertSame([], new DeadManSwitch($this->heartbeats, $interleaved, $locks, $notifier)->watch());
        self::assertSame(1, $notifier->count());
    }

    public function testTheLockIsNamedAfterTheTaskAndHeldAtMostItsDuration(): void
    {
        $store = new class implements PersistingStoreInterface {
            /**
             * @var list<array{string, string, ?float}>
             */
            public array $calls = [];

            /**
             * @var array<string, true>
             */
            private array $held = [];

            public function save(Key $key): void
            {
                $this->calls[] = ['save', (string) $key, null];
                $this->held[(string) $key] = true;
            }

            public function delete(Key $key): void
            {
                $this->calls[] = ['delete', (string) $key, null];
                unset($this->held[(string) $key]);
            }

            public function exists(Key $key): bool
            {
                return isset($this->held[(string) $key]);
            }

            public function putOffExpiration(Key $key, float $ttl): void
            {
                $this->calls[] = ['ttl', (string) $key, $ttl];
            }
        };
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        self::assertSame(['report'], new DeadManSwitch($this->heartbeats, $this->store, new LockFactory($store))->watch());
        self::assertSame([
            ['save', 'phil_monitoring.heartbeat.report', null],
            ['ttl', 'phil_monitoring.heartbeat.report', 30.0],
            ['ttl', 'phil_monitoring.heartbeat.report', 30.0],
            ['ttl', 'phil_monitoring.heartbeat.report', 30.0],
            ['delete', 'phil_monitoring.heartbeat.report', null],
        ], $store->calls, 'Taken, renewed before the alert, renewed before the mark, released.');

        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');
        $store->calls = [];
        new DeadManSwitch($this->heartbeats, $this->store, new LockFactory($store), lockTtl: 5.0)->watch();
        self::assertSame([
            ['save', 'phil_monitoring.heartbeat.report', null],
            ['ttl', 'phil_monitoring.heartbeat.report', 5.0],
            ['ttl', 'phil_monitoring.heartbeat.report', 5.0],
            ['ttl', 'phil_monitoring.heartbeat.report', 5.0],
            ['delete', 'phil_monitoring.heartbeat.report', null],
        ], $store->calls);
    }

    public function testARoundWhoseLockExpiredBeforeTheAlertLeavesTheTaskToTheRoundThatHoldsIt(): void
    {
        $locks = new LockFactory(new ExpiringLockStore($this->clock));
        $slow = new HookedHeartbeats($this->store, function (int $read) use ($locks): void {
            if (2 === $read) {
                $this->clock->sleep(31);
                self::assertTrue($locks->createLock(DeadManSwitch::LOCK_PREFIX.'report', 30.0, false)->acquire());
            }
        });
        $notifier = new SlowNotifier();
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        self::assertSame([], new DeadManSwitch(new Heartbeats([new ExpectedTask('report', 60, 30)], $slow, $this->clock), $slow, $locks, $notifier)->watch());
        self::assertSame(0, $notifier->calls);
        self::assertFalse($this->store->isAlerted('report'));
    }

    public function testAnAlertThatOutlastedItsLockIsLoggedAndMarked(): void
    {
        $locks = new LockFactory(new ExpiringLockStore($this->clock));
        $notifier = new SlowNotifier(function () use ($locks): void {
            $this->clock->sleep(31);
            self::assertTrue($locks->createLock(DeadManSwitch::LOCK_PREFIX.'report', 30.0, false)->acquire());
        });
        $logger = new class extends AbstractLogger {
            /**
             * @var list<array{mixed, string, array<array-key, mixed>}>
             */
            public array $logs = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->logs[] = [$level, (string) $message, $context];
            }
        };
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        self::assertSame(['report'], new DeadManSwitch(new Heartbeats([new ExpectedTask('report', 60, 30)], $this->store, $this->clock), $this->store, $locks, $notifier, logger: $logger)->watch());
        self::assertSame(1, $notifier->calls);
        self::assertTrue($this->store->isAlerted('report'));
        self::assertSame([['warning', 'The alert of the scheduled task {task} outlasted its lock: another round may have sent it too.', ['task' => 'report']]], $logger->logs);
    }

    public function testAnAlertWithinItsLockIsMarkedWithoutAWarning(): void
    {
        $locks = new LockFactory(new ExpiringLockStore($this->clock));
        $notifier = new SlowNotifier(function (): void {
            $this->clock->sleep(29);
        });
        $logger = new class extends AbstractLogger {
            public int $lines = 0;

            public function log($level, string|Stringable $message, array $context = []): void
            {
                ++$this->lines;
            }
        };
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        self::assertSame(['report'], new DeadManSwitch(new Heartbeats([new ExpectedTask('report', 60, 30)], $this->store, $this->clock), $this->store, $locks, $notifier, logger: $logger)->watch());
        self::assertSame(0, $logger->lines);
    }

    public function testALockTheStoreCannotRenewLeavesTheTaskUnalerted(): void
    {
        $store = new ExpiringLockStore($this->clock);
        $failing = new HookedHeartbeats($this->store, static function (int $read) use ($store): void {
            $store->failing = 2 === $read;
        });
        $notifier = new SlowNotifier();
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        self::assertSame([], new DeadManSwitch(new Heartbeats([new ExpectedTask('report', 60, 30)], $failing, $this->clock), $failing, new LockFactory($store), $notifier)->watch());
        self::assertSame(0, $notifier->calls);
        self::assertFalse($this->store->isAlerted('report'));
    }

    public function testTheLockIsReleasedWhenTheAlertFails(): void
    {
        $locks = new LockFactory(new InMemoryStore());
        $notifier = new class implements NotifierInterface {
            public function send(Notification $notification, RecipientInterface ...$recipients): void
            {
                throw new RuntimeException('No transport.');
            }
        };
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        self::assertSame([], new DeadManSwitch($this->heartbeats, $this->store, $locks, $notifier)->watch());
        self::assertTrue($locks->createLock(DeadManSwitch::LOCK_PREFIX.'report')->acquire());
    }

    public function testARoundThatFindsTheLockTakenAlertsNothing(): void
    {
        $locks = new ScriptedLockStore();
        $locks->grants = false;

        $notifier = new RecordingNotifier();
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        self::assertSame([], new DeadManSwitch($this->heartbeats, $this->store, new LockFactory($locks), $notifier)->watch());
        self::assertSame(0, $notifier->count());
        self::assertFalse($this->store->isAlerted('report'));
    }

    public function testAnAlertThatOutlastedItsLockIsMarkedWithoutALogger(): void
    {
        $locks = new LockFactory(new ExpiringLockStore($this->clock));
        $notifier = new SlowNotifier(function () use ($locks): void {
            $this->clock->sleep(31);
            self::assertTrue($locks->createLock(DeadManSwitch::LOCK_PREFIX.'report', 30.0, false)->acquire());
        });
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        self::assertSame(['report'], new DeadManSwitch(new Heartbeats([new ExpectedTask('report', 60, 30)], $this->store, $this->clock), $this->store, $locks, $notifier)->watch());
        self::assertTrue($this->store->isAlerted('report'));
    }

    public function testALockStoreThatFailsIsLogged(): void
    {
        $failing = new class implements PersistingStoreInterface {
            public bool $saves = true;

            public function save(Key $key): void
            {
                if (!$this->saves) {
                    throw new LockStorageException('Down.');
                }
            }

            public function delete(Key $key): void
            {
                throw new LockStorageException('Down.');
            }

            public function exists(Key $key): bool
            {
                return true;
            }

            public function putOffExpiration(Key $key, float $ttl): void
            {
            }
        };
        $logger = new class extends AbstractLogger {
            /**
             * @var list<array{mixed, string, string}>
             */
            public array $logs = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                \assert(\is_string($context['task']) && $context['exception'] instanceof Throwable);
                $this->logs[] = [$level, (string) $message, $context['task']];
            }
        };
        $notifier = new RecordingNotifier();
        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');

        self::assertSame(['report'], new DeadManSwitch($this->heartbeats, $this->store, new LockFactory($failing), $notifier, logger: $logger)->watch());
        self::assertSame([['warning', 'The lock of the scheduled task {task} could not be released; it expires on its own.', 'report']], $logger->logs);

        $this->heartbeats->beat('report');
        $this->clock->modify('+2 minutes');
        $failing->saves = false;
        $logger->logs = [];

        self::assertSame([], new DeadManSwitch($this->heartbeats, $this->store, new LockFactory($failing), $notifier, logger: $logger)->watch());
        self::assertSame([['error', 'The lock of the scheduled task {task} could not be taken.', 'report']], $logger->logs);
        self::assertSame(1, $notifier->count());
        self::assertFalse($this->store->isAlerted('report'));

        self::assertSame([], new DeadManSwitch($this->heartbeats, $this->store, new LockFactory($failing), $notifier)->watch(), 'Without a logger, the round still goes on.');
        self::assertSame(1, $notifier->count());
    }

    public function testTheCheckIsDegradedWhileATaskIsLate(): void
    {
        $check = new HeartbeatsCheck($this->heartbeats);

        self::assertSame('heartbeats', $check->name());
        self::assertSame(HealthStatus::Up, $check->check()->status);
        self::assertSame('2 tasks, none late.', $check->check()->summary);

        $this->heartbeats->beat('backup');
        $this->heartbeats->beat('report');

        $this->clock->modify('+2 hours');

        self::assertSame(HealthStatus::Degraded, $check->check()->status);
        self::assertSame('Late: backup, report.', $check->check()->summary);
    }
}
