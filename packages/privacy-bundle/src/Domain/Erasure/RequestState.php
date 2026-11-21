<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Erasure;

/**
 * Where an erasure request stands, drawn from the states of its modules, except `Failed`, which only an operator decides.
 */
enum RequestState: string
{
    /** At least one module is pending or running, and none needs attention. */
    case Running = 'running';

    /** At least one module needs attention; a replay brings the request back to running. */
    case NeedsAttention = 'needs_attention';

    /** Every module completed. */
    case Completed = 'completed';

    /** Closed by an operator who decided that the request cannot be completed. */
    case Failed = 'failed';

    /**
     * Whether nothing runs for the request any more.
     */
    public function isClosed(): bool
    {
        return self::Completed === $this || self::Failed === $this;
    }
}
