<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application;

use DateTimeImmutable;
use Phil\CommerceConsignmentBundle\Application\Contract\IssueOrderPolicy;
use Phil\CommerceConsignmentBundle\Application\Contract\StockMovements;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\Stock\EntryKind;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Movement;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\OwnerQuantity;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Receipt;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Sale;
use Phil\Money\Money;

/**
 * The shop's stock split by owner: what it receives, sells, moves, takes back and counts, written to the ownership
 * ledger and moved in the stock the other module keeps, in that order — a refusal of the ledger moves nothing.
 *
 * Nothing is committed here: the caller flushes, in one transaction, the ledger and the stock together.
 */
final readonly class ConsignmentStock
{
    public function __construct(
        private Ledger $ledger,
        private StockMovements $stock,
        private IssueOrderPolicy $issueOrders,
    ) {
    }

    /**
     * Receives goods, each line bought or consigned, and adds them to stock.
     *
     * @return list<StockLayer>
     */
    public function receive(Receipt $receipt, DateTimeImmutable $at): array
    {
        $layers = $this->ledger->receive($receipt, $at);
        $this->stock->receive($receipt, $at);

        return $layers;
    }

    /**
     * Starts to keep the owners of units already on hand — stock received before the ledger was installed —, without
     * moving the stock. The ledger never holds more units at a location than the stock has there.
     *
     * @return list<StockLayer>
     */
    public function adopt(Receipt $receipt, DateTimeImmutable $at): array
    {
        foreach ($receipt->quantities() as $sku => $quantity) {
            $layered = $this->ledger->held((string) $sku, $receipt->location) + $quantity;
            $onHand = $this->stock->onHand((string) $sku, $receipt->location);

            if ($layered > $onHand) {
                throw ConsignmentRefused::beyondOnHand((string) $sku, $receipt->location, $layered, $onHand);
            }
        }

        return $this->ledger->receive($receipt, $at, EntryKind::Adopted);
    }

    /**
     * Writes a sale to the ledger, its units taken in the SKU's issue order. The stock itself leaves with the order's
     * shipment, in the other module: `ShippedOrders` writes the sales of shipped orders.
     */
    public function sell(string $reference, string $sku, string $location, int $quantity, Money $unitPrice, DateTimeImmutable $at): Sale
    {
        return $this->ledger->sell($reference, $sku, $location, $quantity, $unitPrice, $this->issueOrders->issueOrder($sku), $at);
    }

    /**
     * Takes units of a sale back to their owners, and puts them on the shelf at the location.
     */
    public function returnSold(string $reference, string $sku, string $location, int $quantity, DateTimeImmutable $at): Sale
    {
        $sale = $this->ledger->returnSold($reference, $sku, $location, $quantity, $at);
        $this->stock->restock($sku, $location, $quantity, $at, $reference);

        return $sale;
    }

    /**
     * Moves units to another location, in the SKU's issue order or of one owner only.
     *
     * @return list<Movement>
     */
    public function transfer(string $sku, string $from, string $to, int $quantity, DateTimeImmutable $at, ?string $reference = null, ?Owner $owner = null): array
    {
        $moved = $this->ledger->transfer($sku, $from, $to, $quantity, $this->issueOrders->issueOrder($sku), $at, $reference, $owner);
        $this->stock->transfer($sku, $from, $to, $quantity, $at, $reference);

        return $moved;
    }

    /**
     * Sets what a count found — of every owner, or of one —, and changes the stock on hand by the difference.
     *
     * @return int the difference: units found when positive, missing when negative
     */
    public function count(string $sku, string $location, int $counted, DateTimeImmutable $at, ?string $reference = null, ?Owner $owner = null): int
    {
        $difference = $this->ledger->count($sku, $location, $counted, $at, $reference, $owner);

        if (0 !== $difference) {
            $this->stock->adjust($sku, $location, $difference, $at, $reference);
        }

        return $difference;
    }

    /**
     * The units of the SKU each owner holds, at one location or at all of them.
     *
     * @return list<OwnerQuantity>
     */
    public function split(string $sku, ?string $location = null): array
    {
        return $this->ledger->split($sku, $location);
    }
}
