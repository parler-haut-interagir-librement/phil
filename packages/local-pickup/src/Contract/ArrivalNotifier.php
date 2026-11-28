<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Contract;

use Phil\LocalPickup\Pickup;

/**
 * Tells the staff of the pickup point that a customer has arrived. Called once per pickup, on the first signal.
 */
interface ArrivalNotifier
{
    public function arrived(Pickup $pickup): void;
}
