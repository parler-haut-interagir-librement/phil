<?php

declare(strict_types=1);

namespace Phil\LocalPickup\Contract;

use Phil\LocalPickup\Exception\CodeLocked;
use Phil\LocalPickup\Exception\CodeRefused;
use Phil\LocalPickup\Exception\PickupRefused;
use Phil\LocalPickup\Exception\UnknownPickup;
use Phil\LocalPickup\HandOver;
use Phil\LocalPickup\HandOverRequest;

/**
 * Hands an order over against its code: the port a counter screen and a point-of-sale register both use, so that a
 * convention booth's register marks a pickup as the store's counter does. Counter implements it.
 */
interface PickupDesk
{
    /**
     * @throws UnknownPickup when no pickup is kept for the order
     * @throws PickupRefused when the pickup is not ready, or has no code yet
     * @throws CodeRefused   when the code is wrong; the attempt is counted
     * @throws CodeLocked    when the code took too many wrong attempts
     */
    public function handOver(HandOverRequest $request): HandOver;
}
