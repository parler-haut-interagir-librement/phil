<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Application;

use DateTimeImmutable;
use Phil\CommerceConsignmentBundle\Application\Contract\StockMovements;
use Phil\CommerceConsignmentBundle\Domain\Contract\ConsignorRepository;
use Phil\CommerceConsignmentBundle\Domain\Contract\LedgerRepository;
use Phil\CommerceConsignmentBundle\Domain\Exception\ConsignmentRefused;
use Phil\CommerceConsignmentBundle\Domain\Exception\InvalidValue;
use Phil\CommerceConsignmentBundle\Domain\Model\ConsignmentAgreement;
use Phil\CommerceConsignmentBundle\Domain\Service\Ledger;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\DueUnits;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\Owner;
use Phil\CommerceConsignmentBundle\Domain\ValueObject\ReturnSlip;
use Phil\Money\Money;

/**
 * The consignors' unsold units: those whose consignment is over, given back to their consignor — off the ledger and off
 * the shelf — or bought by the shop at the agreed cost, where they stand.
 *
 * Nothing is committed here: the caller flushes the ledger and the stock together.
 */
final readonly class UnsoldStock
{
    /** The most consigned layers one listing reads. */
    public const int MAX_SCANNED = 5000;

    public function __construct(
        private Ledger $ledger,
        private LedgerRepository $layers,
        private ConsignorRepository $consignors,
        private StockMovements $stock,
    ) {
    }

    /**
     * The consigned units due at the instant — of one consignor, when given —, the first due first: those whose
     * agreement at their receipt has a term, over by then, extensions included. Units received before a consignor's
     * first agreement, or under one without a term, are never due.
     *
     * @return list<DueUnits>
     */
    public function due(DateTimeImmutable $at, ?Owner $consignor = null, int $limit = 500): array
    {
        $versions = [];
        $due = [];

        foreach ($this->layers->consignedLayers($consignor?->key(), self::MAX_SCANNED) as $layer) {
            $owner = $layer->owner();
            $versions[$owner->key()] ??= $this->consignors->agreementsOf($owner->key());
            $agreement = ConsignorAgreements::versionAt($versions[$owner->key()], $layer->receivedAt());

            if (!$agreement instanceof ConsignmentAgreement) {
                continue;
            }

            $dueAt = $agreement->dueAt($layer->receivedAt());

            if ($dueAt instanceof DateTimeImmutable && $dueAt <= $at) {
                $due[] = new DueUnits($layer->id, $layer->sku, $layer->location, $owner, $layer->remaining(), $layer->receivedAt(), $dueAt, $agreement->actionWhenDue());
            }
        }

        usort($due, static fn (DueUnits $left, DueUnits $right): int => [$left->dueAt, $left->layer] <=> [$right->dueAt, $right->layer]);

        return \array_slice($due, 0, max(1, $limit));
    }

    /**
     * Gives a consignor's units back at a location, SKU by SKU, the oldest receipt first, and takes them off the shelf.
     * More units than the consignor has there, in the ledger or on the shelf, refuses the whole slip before a unit moves.
     *
     * @param array<int|string, int> $quantities SKU → units
     */
    public function giveBack(string $reference, Owner $consignor, string $location, array $quantities, DateTimeImmutable $at): ReturnSlip
    {
        foreach ($quantities as $sku => $quantity) {
            $onHand = $this->stock->onHand((string) $sku, $location);

            if ($quantity > $onHand) {
                throw ConsignmentRefused::shortage((string) $sku, $location, $quantity, $onHand);
            }
        }

        $slip = $this->ledger->giveBack($reference, $consignor, $location, $quantities, $at);

        foreach ($quantities as $sku => $quantity) {
            $this->stock->giveBack((string) $sku, $location, InvalidValue::checkQuantity($quantity), $at, $reference);
        }

        return $slip;
    }

    /**
     * The shop buys a consignor's units at a location, at the agreed unit cost — the consignor's share, when null, on
     * layers with a share —: their owner changes, the stock does not move.
     */
    public function buyOut(string $reference, Owner $consignor, string $sku, string $location, int $quantity, ?Money $unitCost, DateTimeImmutable $at): ReturnSlip
    {
        return $this->ledger->buyOut($reference, $consignor, $sku, $location, $quantity, $unitCost, $at);
    }
}
