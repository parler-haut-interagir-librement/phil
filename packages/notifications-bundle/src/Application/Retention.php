<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Phil\NotificationsBundle\Domain\Contract\NotificationRepository;
use Psr\Clock\ClockInterface;

/**
 * Deletes the notifications kept longer than the retention: the read and archived ones after `$readDays`, the unread
 * ones after `$unreadDays`, a batch at a time, up to `$batches` batches a run so that one run stays short.
 */
final readonly class Retention
{
    public const int BATCH = 1000;

    public const int BATCHES = 100;

    /**
     * @param positive-int $readDays
     * @param positive-int $unreadDays
     * @param positive-int $batch
     * @param positive-int $batches
     */
    public function __construct(
        private NotificationRepository $repository,
        private ClockInterface $clock,
        private int $readDays,
        private int $unreadDays,
        private int $batch = self::BATCH,
        private int $batches = self::BATCHES,
    ) {
    }

    /**
     * @return int how many notifications were deleted
     */
    public function purge(): int
    {
        $now = $this->clock->now();
        $readBefore = $now->modify(\sprintf('-%d days', $this->readDays));
        $unreadBefore = $now->modify(\sprintf('-%d days', $this->unreadDays));
        $deleted = 0;

        for ($run = 0; $run < $this->batches; ++$run) {
            $purged = $this->repository->purge($readBefore, $unreadBefore, $this->batch);
            $deleted += $purged;

            if ($purged < $this->batch) {
                break;
            }
        }

        return $deleted;
    }
}
