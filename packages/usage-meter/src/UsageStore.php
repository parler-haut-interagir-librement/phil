<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

use DateTimeImmutable;

/**
 * Where the counters live. `InMemoryUsageStore` keeps them for one process, `Dbal\DbalUsageStore` in a database.
 *
 * An implementation makes `add()` atomic: two increments of one counter at the same moment are both counted, or one
 * is refused, never lost; a key recorded once is never counted twice; a refused increment leaves neither its amount
 * nor its key behind; a refund never gives back more than its key counted, and never twice.
 */
interface UsageStore
{
    /**
     * Adds `$amount` to the counter, creating it at zero first.
     *
     * @param int|null    $ceiling the most the counter may hold after the increment, or null for no ceiling; it may
     *                             be below zero, which refuses any increment
     * @param string|null $key     the increment's idempotency key, unique per subject and meter; kept as long as the
     *                             counter
     *
     * @phpstan-impure
     */
    public function add(Counter $counter, int $amount, ?int $ceiling = null, ?string $key = null): Increment;

    /**
     * Gives back what was counted under `$key`, so that an action counted and then failed costs nothing.
     *
     * `$amount` is what the key's refunds come to in all, not an addition: a second call with the same amount gives
     * nothing more back, and a smaller one gives nothing. It is bounded by what the key counted; null gives back all
     * of it. The refund goes to the counter the key counted in, even when the window has moved on since.
     *
     * @param int|null $amount one or more, or null for everything the key counted
     *
     * @return int what this call gave back: zero for an unknown or forgotten key, or one refunded that far already
     *
     * @phpstan-impure
     */
    public function refund(string $subject, string $meter, string $key, ?int $amount = null): int;

    /**
     * The sum of a subject's counters of a meter whose start is in [`$from`, `$before`).
     *
     * @phpstan-impure
     */
    public function total(string $subject, string $meter, DateTimeImmutable $from, DateTimeImmutable $before): int;

    /**
     * Forgets the counters, and their keys, that stopped counting by `$now`.
     *
     * @return int how many counters and keys were forgotten
     *
     * @phpstan-impure
     */
    public function purge(DateTimeImmutable $now): int;
}
