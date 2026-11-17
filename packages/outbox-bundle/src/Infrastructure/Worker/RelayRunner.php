<?php

declare(strict_types=1);

namespace Phil\OutboxBundle\Infrastructure\Worker;

use Closure;
use Phil\Outbox\Relay;
use Phil\Outbox\RelayReport;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * Runs the relay batch after batch, for the console command and the scheduled trigger. With a lock factory, one run
 * at a time holds the lock `phil_outbox.relay`, refreshed after each batch, so that a run started while another one
 * runs gives way at once, and the lock is released when the run ends, as the lock factory releases the locks it gave
 * once they are no longer used; without it, concurrent runs still never deliver the same record, since each batch is leased.
 *
 * A run stops after its batches, after its time, when the stop callback says so, or, when asked, as soon as a batch
 * finds nothing; otherwise it sleeps between empty batches. The runner keeps no state between runs.
 */
final readonly class RelayRunner
{
    /** The name of the lock. */
    public const string LOCK = 'phil_outbox.relay';

    public function __construct(
        private Relay $relay,
        private ClockInterface $clock,
        private ?LockFactory $locks = null,
        private int $lease = Relay::LEASE,
    ) {
    }

    /**
     * @param int                               $batchSize     the most records of a batch
     * @param int                               $maxBatches    the most batches, none when 0
     * @param bool                              $stopWhenEmpty whether the run ends at the first batch that finds nothing
     * @param int                               $timeLimit     the seconds after which the run ends, none when 0
     * @param float                             $sleep         the seconds to wait after a batch that found nothing
     * @param (Closure(): bool)|null            $stop          asked after each batch whether to end the run
     * @param (Closure(RelayReport): void)|null $onBatch       told the report of each batch
     *
     * @return RelayReport|null the sum of the batches, or null when another run holds the lock
     */
    public function run(int $batchSize, int $maxBatches = 0, bool $stopWhenEmpty = false, int $timeLimit = 0, float $sleep = 1.0, ?Closure $stop = null, ?Closure $onBatch = null): ?RelayReport
    {
        $lock = $this->locks?->createLock(self::LOCK, 2 * $this->lease);

        if ($lock instanceof SharedLockInterface && !$lock->acquire()) {
            return null;
        }

        $startedAt = $this->clock->now()->getTimestamp();
        $total = new RelayReport();
        $batches = 0;

        while (true) {
            $report = $this->relay->relay($batchSize);
            $total = $total->plus($report);
            ++$batches;
            $lock?->refresh();

            if ($onBatch instanceof Closure) {
                $onBatch($report);
            }

            if (($maxBatches > 0 && $batches >= $maxBatches) || ($stop instanceof Closure && $stop()) || ($report->isEmpty() && $stopWhenEmpty)) {
                return $total;
            }

            if ($report->isEmpty()) {
                $this->clock->sleep($sleep);
            }

            if ($timeLimit > 0 && $this->clock->now()->getTimestamp() - $startedAt >= $timeLimit) {
                return $total;
            }
        }
    }
}
