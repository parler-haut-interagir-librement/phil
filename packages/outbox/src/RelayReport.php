<?php

declare(strict_types=1);

namespace Phil\Outbox;

/**
 * What a run of the relay did: the records it claimed, and of those, the ones delivered, given back to be tried again,
 * declared dead, left untried because the lease ran out, the ones whose mark another claim had taken over, and the ones
 * skipped because what they name was erased.
 */
final readonly class RelayReport
{
    public function __construct(
        public int $claimed = 0,
        public int $delivered = 0,
        public int $retried = 0,
        public int $dead = 0,
        public int $expired = 0,
        public int $lost = 0,
        public int $skipped = 0,
    ) {
    }

    /**
     * Whether the run found nothing to relay.
     */
    public function isEmpty(): bool
    {
        return 0 === $this->claimed;
    }

    /**
     * The sum of two runs.
     */
    public function plus(self $other): self
    {
        return new self(
            $this->claimed + $other->claimed,
            $this->delivered + $other->delivered,
            $this->retried + $other->retried,
            $this->dead + $other->dead,
            $this->expired + $other->expired,
            $this->lost + $other->lost,
            $this->skipped + $other->skipped,
        );
    }
}
