<?php

declare(strict_types=1);

namespace Phil\LocalPickup;

/**
 * Where a pickup stands: being prepared, ready and held at the counter, collected, or held past its hold period.
 */
enum PickupState: string
{
    case Preparing = 'preparing';
    case Ready = 'ready';
    case PickedUp = 'picked_up';
    case Expired = 'expired';

    /**
     * Whether the pickup still holds a place in its slot: prepared or waiting at the counter.
     */
    public function holdsSlot(): bool
    {
        return self::Preparing === $this || self::Ready === $this;
    }
}
