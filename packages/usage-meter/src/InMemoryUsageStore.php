<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

use DateTimeImmutable;
use Override;

/**
 * The counters in an array, for tests and for a single process: a command, a worker that meters what it does in one
 * run. They are lost with the object.
 */
final class InMemoryUsageStore implements UsageStore
{
    /** @var array<string, array<string, array<int, array{used: int, expires: int}>>> subject → meter → start → counter */
    private array $counters = [];

    /** @var array<string, array<string, array<string, array{start: int, amount: int, refunded: int, expires: int}>>> subject → meter → key → increment */
    private array $keys = [];

    #[Override]
    public function add(Counter $counter, int $amount, ?int $ceiling = null, ?string $key = null): Increment
    {
        if (null !== $key && isset($this->keys[$counter->subject][$counter->meter][$key])) {
            return Increment::Duplicate;
        }

        $start = $counter->start->getTimestamp();
        $used = ($this->counters[$counter->subject][$counter->meter][$start]['used'] ?? 0) + $amount;

        if (null !== $ceiling && $used > $ceiling) {
            return Increment::Refused;
        }

        $expires = $counter->expiresAt->getTimestamp();
        $this->counters[$counter->subject][$counter->meter][$start] = ['used' => $used, 'expires' => $expires];

        if (null !== $key) {
            $this->keys[$counter->subject][$counter->meter][$key] = ['start' => $start, 'amount' => $amount, 'refunded' => 0, 'expires' => $expires];
        }

        return Increment::Recorded;
    }

    #[Override]
    public function refund(string $subject, string $meter, string $key, ?int $amount = null): int
    {
        $increment = $this->keys[$subject][$meter][$key] ?? null;

        if (null === $increment || !isset($this->counters[$subject][$meter][$increment['start']])) {
            return 0;
        }

        $given = min($amount ?? $increment['amount'], $increment['amount']) - $increment['refunded'];

        if ($given < 1) {
            return 0;
        }

        $this->keys[$subject][$meter][$key]['refunded'] += $given;
        $this->counters[$subject][$meter][$increment['start']]['used'] -= $given;

        return $given;
    }

    #[Override]
    public function total(string $subject, string $meter, DateTimeImmutable $from, DateTimeImmutable $before): int
    {
        $total = 0;

        foreach ($this->counters[$subject][$meter] ?? [] as $start => $counter) {
            if ($start >= $from->getTimestamp() && $start < $before->getTimestamp()) {
                $total += $counter['used'];
            }
        }

        return $total;
    }

    #[Override]
    public function purge(DateTimeImmutable $now): int
    {
        $forgotten = 0;

        foreach ($this->counters as $subject => $meters) {
            foreach ($meters as $meter => $counters) {
                foreach ($counters as $start => $counter) {
                    if ($counter['expires'] <= $now->getTimestamp()) {
                        unset($this->counters[$subject][$meter][$start]);
                        ++$forgotten;
                    }
                }
            }
        }

        foreach ($this->keys as $subject => $meters) {
            foreach ($meters as $meter => $keys) {
                foreach ($keys as $key => $increment) {
                    if ($increment['expires'] <= $now->getTimestamp()) {
                        unset($this->keys[$subject][$meter][$key]);
                        ++$forgotten;
                    }
                }
            }
        }

        return $forgotten;
    }
}
