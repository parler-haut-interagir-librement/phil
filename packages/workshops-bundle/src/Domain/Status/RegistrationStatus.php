<?php

declare(strict_types=1);

namespace Phil\WorkshopsBundle\Domain\Status;

/**
 * Where a registration stands: its seats taken, or given up. Waiting for seats is the waiting list's part, never a
 * registration's.
 */
enum RegistrationStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    /**
     * Whether the registration still counts: confirmed.
     */
    public function isLive(): bool
    {
        return self::Cancelled !== $this;
    }
}
