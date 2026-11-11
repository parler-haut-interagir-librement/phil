<?php

declare(strict_types=1);

namespace Phil\MonitoringBundle\Application;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\MonitoringBundle\Domain\Contract\HeartbeatStore;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\Exception\LockAcquiringException;
use Symfony\Component\Lock\Exception\LockReleasingException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Notifier\AdminRecipientsProviderInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Throwable;

/**
 * Alerts once per lateness: a task late and not yet alerted is notified, then marked, and stays quiet until it beats
 * again. The notification names the task and its moments only. Without a notifier, the late tasks are marked and
 * returned, for the caller to report. An alert that cannot be sent is logged, and the task left unmarked: the next
 * round tries again.
 *
 * Each task is alerted under a lock named after it, taken without waiting and held at most `$lockTtl` seconds: two
 * workers, or two servers sharing the lock store, never alert the same lateness twice. The round that finds the lock
 * taken skips the task; the one that holds it reads the alert mark again before notifying. A lock store that fails
 * is logged: the task waits for the next round, and a lock that cannot be released expires on its own.
 */
final readonly class DeadManSwitch
{
    /**
     * The prefix of the name of the lock of a task.
     */
    public const string LOCK_PREFIX = 'phil_monitoring.heartbeat.';

    /**
     * @param list<string> $channels the Notifier channels of the alert: "chat", "email", "chat/slack"…
     * @param float        $lockTtl  the seconds a round holds the lock of a task at most
     */
    public function __construct(
        private Heartbeats $heartbeats,
        private HeartbeatStore $store,
        private LockFactory $locks,
        private ?NotifierInterface $notifier = null,
        private array $channels = [],
        private string $importance = Notification::IMPORTANCE_URGENT,
        private ?LoggerInterface $logger = null,
        private float $lockTtl = 30.0,
    ) {
    }

    /**
     * Alerts the tasks late since their last alert.
     *
     * @return list<string> the names of the tasks alerted by this round
     */
    public function watch(): array
    {
        $alerted = [];

        foreach ($this->heartbeats->statuses() as $status) {
            $lateSince = $status->lateSince();

            if (null === $lateSince || $this->store->isAlerted($status->task)) {
                continue;
            }

            if ($this->alert($status->task, $lateSince)) {
                $alerted[] = $status->task;
            }
        }

        return $alerted;
    }

    /**
     * Alerts a late task under its lock: false when another round holds the lock, has alerted it already, or the alert
     * could not be sent.
     */
    private function alert(string $task, DateTimeImmutable $lateSince): bool
    {
        $lock = $this->locks->createLock(self::LOCK_PREFIX.$task, $this->lockTtl, false);

        try {
            if (!$lock->acquire()) {
                return false;
            }
        } catch (LockAcquiringException $lockAcquiringException) {
            $this->logger?->error('The lock of the scheduled task {task} could not be taken.', ['task' => $task, 'exception' => $lockAcquiringException]);

            return false;
        }

        try {
            if ($this->store->isAlerted($task)) {
                return false;
            }

            try {
                $this->notify($task, $lateSince);
            } catch (Throwable $throwable) {
                $this->logger?->error('The lateness of the scheduled task {task} could not be alerted.', ['task' => $task, 'exception' => $throwable]);

                return false;
            }

            $this->store->markAlerted($task);

            return true;
        } finally {
            try {
                $lock->release();
            } catch (LockReleasingException $exception) {
                $this->logger?->warning('The lock of the scheduled task {task} could not be released; it expires on its own.', ['task' => $task, 'exception' => $exception]);
            }
        }
    }

    private function notify(string $task, DateTimeImmutable $lateSince): void
    {
        if (!$this->notifier instanceof NotifierInterface) {
            return;
        }

        $notification = new Notification(\sprintf('Scheduled task "%s" is late', $task), $this->channels)
            ->content(\sprintf('It was expected to run again by %s.', $lateSince->format(DateTimeInterface::ATOM)))
            ->importance($this->importance);

        $recipients = $this->notifier instanceof AdminRecipientsProviderInterface ? $this->notifier->getAdminRecipients() : [];
        $this->notifier->send($notification, ...$recipients);
    }
}
