<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\Enum;

/**
 * Where a send run stands.
 */
enum SendRunStatus: string
{
    /** Its messages are queued and sent. */
    case Running = 'running';

    /** Held by its circuit breaker or by an operator: nothing is sent until an operator resumes it. */
    case Paused = 'paused';

    /** Every message it queued was sent or skipped. */
    case Completed = 'completed';

    /** Stopped by an operator: what it had not sent is skipped. */
    case Cancelled = 'cancelled';

    public function hasEnded(): bool
    {
        return self::Completed === $this || self::Cancelled === $this;
    }
}
