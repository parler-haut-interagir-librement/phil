<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application;

use Phil\PrivacyBundle\Application\Exception\LockLost;
use Phil\PrivacyBundle\Application\Lock\HeldLock;
use Phil\PrivacyBundle\Domain\Retention\PurgeOrder;
use Phil\PrivacyBundle\Domain\Retention\RetentionRule;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Lock\LockFactory;

/**
 * The retention run as a job: at the present instant, one at a time, and with a log line per rule that names the rule
 * and the count, never what was purged.
 *
 * The lock lives `LOCK_TTL` seconds and is renewed before each purge order, so a run may last longer than that as long
 * as each purge order ends within it. A run whose lock expired and was taken by another run stops before its next
 * purge order, logs a warning, and answers as a run that found the lock taken; purges are idempotent, so the run that
 * holds the lock now purges what is left.
 */
final readonly class RetentionJob
{
    public const string LOCK = 'phil_privacy.retention';

    public const float LOCK_TTL = 3600.0;

    private LoggerInterface $logger;

    public function __construct(
        private RetentionRun $run,
        private LockFactory $locks,
        private ClockInterface $clock,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * What a run would purge, refused the same way a run is when a job line has no purger.
     *
     * @return list<PurgeOrder>
     */
    public function plan(): array
    {
        return $this->run->orders($this->clock->now());
    }

    /**
     * @return list<RetentionRule>
     */
    public function jobsWithoutCutoff(): array
    {
        return $this->run->jobsWithoutCutoff();
    }

    /**
     * @return array<string, int>|null records purged by rule identifier, or null when another run holds the lock or
     *                                 took it over during this one
     */
    public function run(): ?array
    {
        $lock = $this->locks->createLock(self::LOCK, self::LOCK_TTL, false);

        if (!$lock->acquire()) {
            return null;
        }

        $held = new HeldLock($lock);

        try {
            $purged = $this->run->run($this->clock->now(), $held);
        } catch (LockLost) {
            $this->logger->warning('The retention run lost its lock and stopped before its next rule.');

            return null;
        } finally {
            $held->release();
        }

        foreach ($purged as $rule => $count) {
            $this->logger->info('Retention rule {rule}: {count} record(s) purged.', ['rule' => $rule, 'count' => $count]);
        }

        return $purged;
    }
}
