<?php

declare(strict_types=1);

namespace Phil\AccessCodes;

/**
 * The counters of a batch: how many codes it holds, how many were redeemed, how many were revoked before being
 * redeemed. The rest can still be redeemed, unless the batch has expired.
 */
final readonly class BatchCounts
{
    public function __construct(
        public int $total,
        public int $redeemed,
        public int $revoked,
    ) {
    }

    /**
     * The codes neither redeemed nor revoked.
     */
    public function unused(): int
    {
        return $this->total - $this->redeemed - $this->revoked;
    }
}
