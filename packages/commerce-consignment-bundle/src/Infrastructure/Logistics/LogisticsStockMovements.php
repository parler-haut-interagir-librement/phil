<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Infrastructure\Logistics;

use DateTimeImmutable;
use Override;
use Phil\CommerceConsignmentBundle\Application\Contract\StockMovements;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceLogisticsBundle\Application\Purchasing;
use Phil\CommerceLogisticsBundle\Application\Stock;
use Phil\CommerceLogisticsBundle\Domain\Exception\PurchaseRefused;
use Phil\CommerceLogisticsBundle\Domain\Model\PurchaseOrder;
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Purchasing\PurchaseLine;
use Phil\CommerceLogisticsBundle\Domain\Repository\PurchaseOrderRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\Money\Money;

/**
 * The stock of `phil/commerce-logistics-bundle`, moved with what its public services offer.
 *
 * A receipt against a purchase order goes through `Purchasing::receive()`, which receives at the order's location and
 * counts the units against it, once each line agrees with the owner and the unit cost the order gives; any other
 * receipt through `Stock::receive()`. A count changes the level by the difference the ledger found, through
 * `Stock::adjust()`, never below zero. A return of an order the logistics module shipped puts the units back through
 * `Stock::returnFromCustomer()`; a sale it never shipped, a counter sale, is received again under its reference. Units
 * given back to their consignor leave through `Stock::adjust()`.
 */
final readonly class LogisticsStockMovements implements StockMovements
{
    public function __construct(
        private Stock $stock,
        private StockRepository $levels,
        private Purchasing $purchasing,
        private PurchaseOrderRepository $purchaseOrders,
    ) {
    }

    #[Override]
    public function receive(Receipt $receipt, DateTimeImmutable $at): void
    {
        if (null === $receipt->purchaseOrder) {
            foreach ($receipt->quantities() as $sku => $quantity) {
                $this->stock->receive((string) $sku, $receipt->location, $quantity, $at, $receipt->reference);
            }

            return;
        }

        $order = $this->purchaseOrders->findPurchaseOrder($receipt->purchaseOrder) ?? throw PurchaseRefused::unknown($receipt->purchaseOrder);

        if ($order->location !== $receipt->location) {
            throw ConsignmentRefused::purchaseOrderElsewhere($order->number, $order->location, $receipt->location);
        }

        $this->checkAgainst($order, $receipt);
        $this->purchasing->receive($order->number, $receipt->quantities(), $at);
    }

    #[Override]
    public function transfer(string $sku, string $from, string $to, int $quantity, DateTimeImmutable $at, ?string $reference): void
    {
        $this->stock->transfer($sku, $from, $to, $quantity, $at, $reference);
    }

    #[Override]
    public function adjust(string $sku, string $location, int $difference, DateTimeImmutable $at, ?string $reference): void
    {
        // The ledger may hold units the logistics module already shipped, before their sale is written: a count in
        // between takes off what is on hand at most.
        $difference = max($difference, -$this->onHand($sku, $location));

        if (0 !== $difference) {
            $this->stock->adjust($sku, $location, $difference, $at, $reference);
        }
    }

    #[Override]
    public function restock(string $sku, string $location, int $quantity, DateTimeImmutable $at, string $reference): void
    {
        foreach ($this->levels->reservationsOf($reference) as $reservation) {
            if ($reservation->sku === $sku) {
                $this->stock->returnFromCustomer($reference, $sku, $location, $quantity, $at);

                return;
            }
        }

        $this->stock->receive($sku, $location, $quantity, $at, $reference);
    }

    #[Override]
    public function giveBack(string $sku, string $location, int $quantity, DateTimeImmutable $at, string $reference): void
    {
        $onHand = $this->onHand($sku, $location);

        if ($quantity > $onHand) {
            throw ConsignmentRefused::shortage($sku, $location, $quantity, $onHand);
        }

        $this->stock->adjust($sku, $location, -$quantity, $at, $reference);
    }

    #[Override]
    public function onHand(string $sku, string $location): int
    {
        $level = $this->levels->level($sku, $location);

        return $level instanceof StockLevel ? $level->onHand() : 0;
    }

    /**
     * Refuses a line consigned by another owner than the one the purchase order names for its SKU, or bought at another
     * unit cost than the order's; what the order leaves out is not checked.
     */
    private function checkAgainst(PurchaseOrder $order, Receipt $receipt): void
    {
        foreach ($receipt->lines as $line) {
            $ordered = $order->line($line->sku);

            if (!$ordered instanceof PurchaseLine) {
                continue;
            }

            if (null !== $ordered->owner && $ordered->owner !== $line->owner->consignor) {
                throw ConsignmentRefused::purchaseOrderOwner($order->number, $line->sku, $ordered->owner, $line->owner->describe());
            }

            if ($ordered->unitCost instanceof Money && $line->unitCost instanceof Money && !$ordered->unitCost->equals($line->unitCost)) {
                throw ConsignmentRefused::purchaseOrderCost($order->number, $line->sku, $ordered->unitCost, $line->unitCost);
            }
        }
    }
}
