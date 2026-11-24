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
use Phil\CommerceLogisticsBundle\Domain\Model\StockLevel;
use Phil\CommerceLogisticsBundle\Domain\Repository\PurchaseOrderRepository;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;

/**
 * The stock of `phil/commerce-logistics-bundle`, moved with what its public services offer.
 *
 * A receipt against a purchase order goes through `Purchasing::receive()`, which receives at the order's location and
 * counts the units against it; any other receipt through `Stock::receive()`. A count sets the level to what it had on
 * hand plus the difference the ledger found, through `Stock::count()`. A return is received again under the sale's
 * reference: the logistics journal has no movement for returns, and writes it as a receipt. Units given back to their
 * consignor leave through `Stock::count()` too, the level set to what it had on hand less the units: the logistics
 * module has no movement that takes units off the shelf by a quantity.
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
        $this->stock->count($sku, $location, max(0, $this->onHand($sku, $location) + $difference), $at, $reference);
    }

    #[Override]
    public function restock(string $sku, string $location, int $quantity, DateTimeImmutable $at, string $reference): void
    {
        $this->stock->receive($sku, $location, $quantity, $at, $reference);
    }

    #[Override]
    public function giveBack(string $sku, string $location, int $quantity, DateTimeImmutable $at, string $reference): void
    {
        $onHand = $this->onHand($sku, $location);

        if ($quantity > $onHand) {
            throw ConsignmentRefused::shortage($sku, $location, $quantity, $onHand);
        }

        $this->stock->count($sku, $location, $onHand - $quantity, $at, $reference);
    }

    #[Override]
    public function onHand(string $sku, string $location): int
    {
        $level = $this->levels->level($sku, $location);

        return $level instanceof StockLevel ? $level->onHand() : 0;
    }
}
