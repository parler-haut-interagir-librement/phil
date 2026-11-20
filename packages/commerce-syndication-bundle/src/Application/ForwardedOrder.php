<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use Phil\Money\Money;

/**
 * An order a reseller sold and forwarded here to be shipped: its number at the reseller, the lines — SKU, quantity
 * and the unit cost the reseller owes —, and where to ship, as the reseller described it.
 */
final readonly class ForwardedOrder
{
    /**
     * @param list<array{sku: string, quantity: int, unitCost: Money}> $lines
     * @param array<mixed>                                             $shipTo
     */
    public function __construct(
        public string $reseller,
        public string $orderNumber,
        public array $lines,
        public array $shipTo,
        public Money $owed,
    ) {
    }
}
