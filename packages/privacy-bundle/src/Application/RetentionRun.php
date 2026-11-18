<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application;

use DateTimeImmutable;
use Phil\PrivacyBundle\Application\Contract\RetentionPurger;
use Phil\PrivacyBundle\Application\Contract\RetentionScheduleSource;
use Phil\PrivacyBundle\Application\Exception\LockLost;
use Phil\PrivacyBundle\Application\Exception\MisconfiguredPrivacy;
use Phil\PrivacyBundle\Application\Lock\HeldLock;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;

/**
 * Reads the retention schedule and hands each purge order to the purger of its store.
 *
 * **Every job line with a fixed period must have a purger, checked before anything is purged.** A rule that says
 * "job" with nothing behind it is a period stated and not enforced, which is the failure a retention schedule
 * exists to prevent; a run that purged the other stores first would report it after the fact, as a partial
 * success. A job line whose period runs until an event or is not fixed yet yields no purge order, so it is not
 * checked here: `RetentionSchedule::jobsWithoutCutoff()` lists those for the application.
 *
 * This is the body of the job, called with the instant it runs at; `RetentionJob` runs it one at a time.
 */
final readonly class RetentionRun
{
    /**
     * @var array<string, RetentionPurger>
     */
    private array $purgers;

    /**
     * @param iterable<RetentionPurger> $purgers
     */
    public function __construct(
        private RetentionScheduleSource $schedule,
        iterable $purgers,
    ) {
        $byStore = [];

        foreach ($purgers as $purger) {
            $store = $purger->store();

            if (isset($byStore[$store])) {
                throw MisconfiguredPrivacy::duplicatePurger($store);
            }

            $byStore[$store] = $purger;
        }

        $this->purgers = $byStore;
    }

    /**
     * Under a held lock, the lock is renewed before each purge order: a run that lost it stops before the next one.
     *
     * @return array<string, int> records purged, by rule identifier, in schedule order
     *
     * @throws LockLost when the held lock is lost before a purge order
     */
    public function run(DateTimeImmutable $now, ?HeldLock $lock = null): array
    {
        $purged = [];

        foreach ($this->orders($now) as $order) {
            $lock?->renew();
            $purged[$order->ruleId] = $this->purgers[$order->store]->purge($order);
        }

        return $purged;
    }

    /**
     * What a run at this instant would purge, in schedule order, or the refusal it would meet.
     *
     * @return list<PurgeOrder>
     *
     * @throws MisconfiguredPrivacy a job line has no purger
     */
    public function orders(DateTimeImmutable $now): array
    {
        $orders = $this->schedule->load()->purgeOrders($now);
        $missing = [];

        foreach ($orders as $order) {
            if (!isset($this->purgers[$order->store])) {
                $missing[$order->store][] = $order->ruleId;
            }
        }

        foreach ($missing as $store => $rules) {
            throw MisconfiguredPrivacy::missingPurger($store, $rules);
        }

        return $orders;
    }

    /**
     * @return list<RetentionRule>
     */
    public function jobsWithoutCutoff(): array
    {
        return $this->schedule->load()->jobsWithoutCutoff();
    }
}
