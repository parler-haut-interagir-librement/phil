<?php

declare(strict_types=1);

namespace Phil\Shifts\Swap;

/**
 * Where an offer of the swap board stands: open to every volunteer, taken and waiting for a coordinator, completed —
 * the place passed —, or cancelled by the one who offered it.
 */
enum SwapStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return self::Open === $this || self::Pending === $this;
    }
}
