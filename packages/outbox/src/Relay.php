<?php

declare(strict_types=1);

namespace Phil\Outbox;

use Phil\Outbox\Contract\OutboxStore;
use Phil\Outbox\Exception\ForgottenRecord;
use Phil\Outbox\Exception\InvalidRecord;
use Phil\Outbox\Exception\InvalidSetting;
use Phil\Outbox\Exception\UndeliverableRecord;
use Phil\Outbox\Exception\UnknownEventType;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Tells the listeners what the records say, at least once. A run claims a batch of records under a lease, so that
 * concurrent relays never deliver the same record while it runs, rebuilds each event through its codec, dispatches it,
 * and marks the record delivered only once the dispatch returned. A failure gives the record back, to be tried again
 * after the delay of the backoff, or declares it dead after its last attempt; a record with a payload that is not one,
 * or that its codec declares undeliverable is dead at once. A record that its codec declares forgotten, because what it
 * names was erased on purpose, is skipped: no listener receives it, and it is never dead. A record of a type no codec
 * rebuilds is tried again during the grace of unknown types, counted from when it happened, so that a worker deployed
 * before the code that records the type does not bury it, and is dead after. The records of an ordering key go one
 * after the other: the store holds back a record while an earlier one of its key is not delivered or skipped.
 *
 * The relay keeps no state between runs: one instance serves every run of a worker.
 */
final readonly class Relay
{
    /** The records a run claims, unless the caller chooses otherwise. */
    public const int BATCH = 100;

    /** The most records a run claims. */
    public const int MAX_BATCH = 1000;

    /** How long a claim holds its records, in seconds, unless the application chooses otherwise. */
    public const int LEASE = 60;

    /** How long a record of an unknown type is tried again, in seconds, unless the application chooses otherwise: one hour. */
    public const int UNKNOWN_TYPE_GRACE = 3600;

    /** The longest grace of unknown types, in seconds: seven days. */
    public const int MAX_UNKNOWN_TYPE_GRACE = 604_800;

    /**
     * @param int $lease            how long a claim holds its records, in seconds, from 1 to 3,600: longer than the
     *                              slowest listener, or another relay claims the record again and it is delivered twice
     * @param int $unknownTypeGrace how long after it happened a record of an unknown type is tried again, in seconds,
     *                              from 0 to 604,800: 0 buries it at once
     *
     * @throws InvalidSetting when the lease or the grace is out of bounds
     */
    public function __construct(
        private OutboxStore $store,
        private Codecs $codecs,
        private EventDispatcherInterface $dispatcher,
        private ClockInterface $clock = new SystemClock(),
        private Backoff $backoff = new Backoff(),
        private int $lease = self::LEASE,
        private int $unknownTypeGrace = self::UNKNOWN_TYPE_GRACE,
    ) {
        if ($lease < 1 || $lease > 3600) {
            throw InvalidSetting::because(\sprintf('A lease lasts from 1 to 3600 seconds, not %d.', $lease));
        }

        if ($unknownTypeGrace < 0 || $unknownTypeGrace > self::MAX_UNKNOWN_TYPE_GRACE) {
            throw InvalidSetting::because(\sprintf('The grace of unknown types lasts from 0 to %d seconds, not %d.', self::MAX_UNKNOWN_TYPE_GRACE, $unknownTypeGrace));
        }
    }

    /**
     * Relays one batch of records.
     *
     * @param int $limit the most records to claim, from 1 to 1,000
     *
     * @throws InvalidSetting when the limit is out of bounds
     */
    public function relay(int $limit = self::BATCH): RelayReport
    {
        if ($limit < 1 || $limit > self::MAX_BATCH) {
            throw InvalidSetting::because(\sprintf('A batch holds from 1 to %d records, not %d.', self::MAX_BATCH, $limit));
        }

        $claim = sodium_bin2hex(random_bytes(16));
        $now = $this->clock->now();
        $leaseUntil = $now->modify(\sprintf('+%d seconds', $this->lease));
        $records = $this->store->claim($claim, $limit, $now, $leaseUntil);
        $report = new RelayReport(claimed: \count($records));

        foreach ($records as $index => $record) {
            if ($this->clock->now() >= $leaseUntil) {
                return $report->plus(new RelayReport(expired: \count($records) - $index));
            }

            $report = $report->plus($this->deliver($record, $claim));
        }

        return $report;
    }

    /**
     * @param non-empty-string $claim
     */
    private function deliver(OutboxRecord $record, string $claim): RelayReport
    {
        $attempts = $record->attempts + 1;

        try {
            $event = $this->codecs->get($record->type)->decode($record, $record->payload());
        } catch (ForgottenRecord) {
            return $this->store->markSkipped($record->id, $claim, $this->clock->now())
                ? new RelayReport(skipped: 1)
                : new RelayReport(lost: 1);
        } catch (UnknownEventType $unknownEventType) {
            return $this->awaitType($record, $claim, $attempts, $unknownEventType);
        } catch (InvalidRecord|UndeliverableRecord $undeliverable) {
            return $this->bury($record, $claim, $attempts, $undeliverable);
        } catch (Throwable $throwable) {
            return $this->fail($record, $claim, $attempts, $throwable);
        }

        try {
            $this->dispatcher->dispatch($event);
        } catch (Throwable $throwable) {
            return $this->fail($record, $claim, $attempts, $throwable);
        }

        return $this->store->markDelivered($record->id, $claim, $this->clock->now())
            ? new RelayReport(delivered: 1)
            : new RelayReport(lost: 1);
    }

    /**
     * @param non-empty-string $claim
     */
    private function fail(OutboxRecord $record, string $claim, int $attempts, Throwable $throwable): RelayReport
    {
        if ($this->backoff->exhausted($attempts)) {
            return $this->bury($record, $claim, $attempts, $throwable);
        }

        return $this->store->release($record->id, $claim, $attempts, OutboxRecord::error($throwable), $this->backoff->retryAt($attempts, $this->clock->now()))
            ? new RelayReport(retried: 1)
            : new RelayReport(lost: 1);
    }

    /**
     * Tries a record of an unknown type again while its grace runs, never later than its end, whatever the attempts:
     * the code that rebuilds it may be on its way to this worker. Buries it once the grace is over.
     *
     * @param non-empty-string $claim
     */
    private function awaitType(OutboxRecord $record, string $claim, int $attempts, UnknownEventType $unknownEventType): RelayReport
    {
        $now = $this->clock->now();
        $graceEnd = $record->occurredAt->modify(\sprintf('+%d seconds', $this->unknownTypeGrace));

        if ($now >= $graceEnd) {
            return $this->bury($record, $claim, $attempts, $unknownEventType);
        }

        return $this->store->release($record->id, $claim, $attempts, OutboxRecord::error($unknownEventType), min($this->backoff->retryAt($attempts, $now), $graceEnd))
            ? new RelayReport(retried: 1)
            : new RelayReport(lost: 1);
    }

    /**
     * @param non-empty-string $claim
     */
    private function bury(OutboxRecord $record, string $claim, int $attempts, Throwable $throwable): RelayReport
    {
        return $this->store->markDead($record->id, $claim, $attempts, OutboxRecord::error($throwable), $this->clock->now())
            ? new RelayReport(dead: 1)
            : new RelayReport(lost: 1);
    }
}
