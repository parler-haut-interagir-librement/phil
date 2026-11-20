<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

use DateTimeImmutable;

/**
 * A subject's use of a meter in the current window: how much is used, the limit, and when the window next changes.
 */
final readonly class Usage
{
    public function __construct(
        public string $subject,
        public string $meter,
        public int $used,
        public Limit $limit,
        /** The first instant the window counts. */
        public DateTimeImmutable $start,
        /** When the window next changes: a new period, or the oldest bucket of a rolling window leaving it. */
        public DateTimeImmutable $resetsAt,
        /** Whether this answers an increment whose key was already counted, and so was not counted again. */
        public bool $replayed,
    ) {
    }

    /**
     * @return int|null what is left, never below zero; null for no limit
     */
    public function remaining(): ?int
    {
        return $this->limit->remaining($this->used);
    }

    /**
     * Whether `$amount` more fits in the quota.
     */
    public function allows(int $amount = 1): bool
    {
        return $this->limit->allows($this->used, $amount);
    }

    /**
     * Whether more is used than the limit: a `record()` counts past it, a lowered limit leaves a window over it.
     */
    public function isExceeded(): bool
    {
        return !$this->limit->allows($this->used, 0);
    }
}
