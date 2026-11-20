<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Application;

use Phil\Money\Money;

/**
 * What a terminal is asked to charge: the payment of the core it settles, its order, the processor's device, and the
 * amount — the payment's own, never one taken from the till's request.
 */
final readonly class TerminalCharge
{
    public function __construct(
        public string $payment,
        public string $orderNumber,
        public string $device,
        public Money $amount,
    ) {
    }
}
