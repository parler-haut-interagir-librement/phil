<?php

declare(strict_types=1);

namespace Phil\FollowBundle\Application\Handler;

use DateInterval;
use Phil\FollowBundle\Domain\Contract\ActivityRepository;
use Phil\FollowBundle\Domain\Contract\DeliveryRepository;
use Phil\FollowBundle\Domain\Exception\InvalidFollow;
use Psr\Clock\ClockInterface;

/**
 * Deletes, a thousand rows at a time and a hundred times at most a run, the activities older than their retention and
 * the claims of their deliveries, and the marks of notifications older than the delay that holds the next ones back.
 */
final readonly class PurgeFollowRecordsHandler
{
    public const int BATCH = 1000;

    public const int BATCHES = 100;

    /**
     * @throws InvalidFollow when a period is out of range
     */
    public function __construct(
        private ActivityRepository $activities,
        private DeliveryRepository $deliveries,
        private ClockInterface $clock,
        private int $activityDays = 30,
        private int $resendAfter = 604800,
    ) {
        if ($activityDays < 1) {
            throw InvalidFollow::because('Activities are kept 1 day or more.');
        }

        if ($resendAfter < 0) {
            throw InvalidFollow::because('The delay before a notification goes out on every channel again is 0 seconds or more.');
        }
    }

    /**
     * @return int how many rows were deleted
     */
    public function __invoke(): int
    {
        $now = $this->clock->now();
        $activities = $now->sub(new DateInterval('P'.$this->activityDays.'D'));
        $marks = $now->sub(new DateInterval('PT'.$this->resendAfter.'S'));

        return $this->repeat(fn (): int => $this->activities->purgeBefore($activities, self::BATCH))
            + $this->repeat(fn (): int => $this->deliveries->purgeClaimsBefore($activities, self::BATCH))
            + $this->repeat(fn (): int => $this->deliveries->purgeMarksBefore($marks, self::BATCH));
    }

    /**
     * @param callable(): int $purge
     */
    private function repeat(callable $purge): int
    {
        $total = 0;

        for ($round = 0; $round < self::BATCHES; ++$round) {
            $deleted = $purge();
            $total += $deleted;

            if ($deleted < self::BATCH) {
                break;
            }
        }

        return $total;
    }
}
