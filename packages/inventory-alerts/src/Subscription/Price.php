<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Subscription;

use Phil\InventoryAlerts\Exception\InvalidInput;
use Phil\InventoryAlerts\Internal\Check;

/**
 * A price in the minor units of an ISO 4217 currency — cents for CAD —, the threshold of a price-drop alert or the
 * new price of a SKU.
 */
final readonly class Price
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {
        Check::units('price', $amount);

        if (1 !== preg_match('/^[A-Z]{3}$/D', $currency)) {
            throw InvalidInput::because('currency', 'must be three capital letters (ISO 4217)');
        }
    }

    /**
     * Whether this price is at or below a threshold of the same currency.
     */
    public function isAtOrBelow(self $threshold): bool
    {
        return $this->currency === $threshold->currency && $this->amount <= $threshold->amount;
    }
}
