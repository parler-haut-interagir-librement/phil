<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Infrastructure\Logistics;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Model\Reservation;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommerceReturnsBundle\Application\Port\Restocking;
use Phil\CommerceReturnsBundle\Domain\Exception\ReturnRefused;
use Phil\CommerceReturnsBundle\Domain\Model\ReturnRequest;
use Phil\CommerceReturnsBundle\Domain\Repository\ReturnRepository;

/**
 * `Restocking` on `phil/commerce-logistics-bundle`: the resellable units of a product line go back on a shelf through
 * `Stock::returnFromCustomer()`, under the return's order, at the configured location or, without one, where the
 * order's units of that SKU left from. With the logistics module's costing, they come back at the cost they left at,
 * never at the day's cost.
 *
 * A line that is no product, or a SKU the order never reserved — one the stock does not track —, puts nothing back.
 */
final readonly class LogisticsRestocking implements Restocking
{
    /** The reference type of a product of the catalogue, which the stock tracks by its SKU. */
    private const string PRODUCT = 'product';

    /**
     * @param string|null $location the code of the location every return goes to — a returns area —; null for the one
     *                              each SKU left from
     */
    public function __construct(
        private Stock $stock,
        private StockRepository $levels,
        private ReturnRepository $returns,
        private ?string $location = null,
    ) {
    }

    #[Override]
    public function restock(SellableReference $line, int $quantity, string $return, DateTimeImmutable $at): void
    {
        $sellable = $line->unconfigured();

        if (self::PRODUCT !== $sellable->type) {
            return;
        }

        $request = $this->returns->findReturn($return);

        if (!$request instanceof ReturnRequest) {
            throw ReturnRefused::unknown($return);
        }

        $reservations = array_values(array_filter(
            $this->levels->reservationsOf($request->orderNumber),
            static fn (Reservation $reservation): bool => $reservation->sku === $sellable->id,
        ));

        if ([] === $reservations) {
            return;
        }

        $this->stock->returnFromCustomer($request->orderNumber, $sellable->id, $this->location ?? $this->leftFrom($reservations), $quantity, $at);
    }

    /**
     * Where the units still to come back left from: the first reservation with units out, else the first one.
     *
     * @param non-empty-list<Reservation> $reservations
     */
    private function leftFrom(array $reservations): string
    {
        foreach ($reservations as $reservation) {
            if ($reservation->returnable() > 0) {
                return $reservation->location;
            }
        }

        return $reservations[0]->location;
    }
}
