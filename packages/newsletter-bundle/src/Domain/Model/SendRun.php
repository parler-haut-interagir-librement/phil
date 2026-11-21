<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Model;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Enum\PauseReason;
use Phil\NewsletterBundle\Domain\Enum\SendRunCounter;
use Phil\NewsletterBundle\Domain\Enum\SendRunStatus;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;

/**
 * One attempt at sending a campaign: the messages it queued, what became of them, and where it stands. Its id is
 * drawn from the campaign and its number, so that the same attempt is kept once.
 *
 * Its counts are added to in the store by each worker that sends one of its messages, never written over; the
 * circuit breaker reads them since the run started or since an operator last resumed it.
 */
final class SendRun
{
    /** The counts the circuit breaker reads. */
    public const array BREAKER_COUNTERS = [SendRunCounter::Sent, SendRunCounter::Failed, SendRunCounter::Bounced, SendRunCounter::Complained];

    public private(set) SendRunStatus $status = SendRunStatus::Running;

    public private(set) ?PauseReason $pauseReason = null;

    /** How many messages it queued, known once they are all queued. */
    public private(set) int $queued = 0;

    public private(set) ?DateTimeImmutable $queuedAt = null;

    public private(set) ?DateTimeImmutable $pausedAt = null;

    public private(set) ?DateTimeImmutable $endedAt = null;

    /** @var array<value-of<SendRunCounter>, int> */
    private array $counts = [];

    /** @var array<value-of<SendRunCounter>, int> what the counts were when an operator last resumed the run */
    private array $baseline = [];

    /**
     * @throws InvalidNewsletterValue when the number is not positive
     */
    public function __construct(
        public readonly string $id,
        public readonly string $campaignId,
        public readonly int $number,
        public readonly DateTimeImmutable $startedAt,
    ) {
        if ($number < 1) {
            throw InvalidNewsletterValue::because('The number of a send run is positive.');
        }

        foreach (SendRunCounter::cases() as $counter) {
            $this->counts[$counter->value] = 0;
        }

        foreach (self::BREAKER_COUNTERS as $counter) {
            $this->baseline[$counter->value] = 0;
        }
    }

    /**
     * The id of a campaign's send run of that number.
     */
    public static function idFor(string $campaignId, int $number): string
    {
        return substr(hash('sha256', 'run'."\0".$campaignId."\0".$number), 0, 32);
    }

    /**
     * Restores a send run as a store keeps it.
     *
     * @param array<value-of<SendRunCounter>, int> $counts
     * @param array<value-of<SendRunCounter>, int> $baseline
     */
    public static function restore(self $run, SendRunStatus $status, ?PauseReason $pauseReason, int $queued, array $counts, array $baseline, ?DateTimeImmutable $queuedAt, ?DateTimeImmutable $pausedAt, ?DateTimeImmutable $endedAt): self
    {
        $run->status = $status;
        $run->pauseReason = $pauseReason;
        $run->queued = $queued;
        $run->counts = $counts + $run->counts;
        $run->baseline = $baseline + $run->baseline;
        $run->queuedAt = $queuedAt;
        $run->pausedAt = $pausedAt;
        $run->endedAt = $endedAt;

        return $run;
    }

    public function count(SendRunCounter $counter): int
    {
        return $this->counts[$counter->value];
    }

    /**
     * The count since the run started, or since an operator last resumed it.
     */
    public function sinceResume(SendRunCounter $counter): int
    {
        return $this->count($counter) - ($this->baseline[$counter->value] ?? 0);
    }

    /**
     * Every message of the run is queued: how many there are.
     */
    public function queuedAll(int $queued, DateTimeImmutable $now): void
    {
        $this->queued = $queued;
        $this->queuedAt = $now;
    }

    /**
     * Every message of the run is queued, and each was sent or skipped.
     */
    public function isDone(): bool
    {
        return $this->queuedAt instanceof DateTimeImmutable && $this->count(SendRunCounter::Sent) + $this->count(SendRunCounter::Skipped) >= $this->queued;
    }

    public function pause(PauseReason $reason, DateTimeImmutable $now): void
    {
        $this->expect([SendRunStatus::Running], 'paused');
        $this->status = SendRunStatus::Paused;
        $this->pauseReason = $reason;
        $this->pausedAt = $now;
    }

    /**
     * Sends again what the run holds; the circuit breaker counts from here.
     */
    public function resume(): void
    {
        $this->expect([SendRunStatus::Paused], 'resumed');
        $this->status = SendRunStatus::Running;
        $this->pauseReason = null;
        $this->pausedAt = null;

        foreach (self::BREAKER_COUNTERS as $counter) {
            $this->baseline[$counter->value] = $this->count($counter);
        }
    }

    public function complete(DateTimeImmutable $now): void
    {
        $this->expect([SendRunStatus::Running], 'completed');

        if (!$this->isDone()) {
            throw InvalidNewsletterValue::because('A send run is completed once every message it queued was sent or skipped.');
        }

        $this->status = SendRunStatus::Completed;
        $this->endedAt = $now;
    }

    public function cancel(DateTimeImmutable $now): void
    {
        $this->expect([SendRunStatus::Running, SendRunStatus::Paused], 'cancelled');
        $this->status = SendRunStatus::Cancelled;
        $this->endedAt = $now;
    }

    /**
     * @return array<value-of<SendRunCounter>, int> what the counts were when an operator last resumed the run
     */
    public function baseline(): array
    {
        return $this->baseline;
    }

    /**
     * @param list<SendRunStatus> $allowed
     */
    private function expect(array $allowed, string $what): void
    {
        if (!\in_array($this->status, $allowed, true)) {
            throw InvalidNewsletterValue::because(\sprintf('A send run that is %s cannot be %s.', $this->status->value, $what));
        }
    }
}
