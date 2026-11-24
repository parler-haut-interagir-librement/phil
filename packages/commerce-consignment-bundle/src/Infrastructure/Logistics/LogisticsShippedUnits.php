<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Infrastructure\Logistics;

use Override;
use Phil\CommerceConsignmentBundle\Application\Contract\ShippedUnits;
use Phil\CommerceConsignmentBundle\Application\ShippedQuantity;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;

/**
 * What an order shipped, read from its reservations in `phil/commerce-logistics-bundle`: each reservation holds a SKU
 * at a location, and what it shipped is its quantity less what it still holds, or gave back.
 *
 * The logistics module dispatches no event when units ship: the application calls `ShippedOrders` after
 * `Stock::ship()` or `Stock::shipPart()`, or sends `RecordShipment` to a worker.
 */
final readonly class LogisticsShippedUnits implements ShippedUnits
{
    public function __construct(
        private StockRepository $stock,
    ) {
    }

    #[Override]
    public function shipped(string $orderNumber): array
    {
        $shipped = [];

        foreach ($this->stock->reservationsOf($orderNumber) as $reservation) {
            $quantity = $reservation->quantity - $reservation->remaining();

            if ($quantity > 0) {
                $key = $reservation->sku."\0".$reservation->location;
                $shipped[$key] = new ShippedQuantity($reservation->sku, $reservation->location, ($shipped[$key]->quantity ?? 0) + $quantity);
            }
        }

        return array_values($shipped);
    }
}
