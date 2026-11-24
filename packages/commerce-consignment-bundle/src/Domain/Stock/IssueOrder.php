<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\Stock;

use Phil\CommerceConsignmentBundle\Domain\Model\StockLayer;

/**
 * Which units of a SKU leave first when the shop holds some it bought and some on consignment.
 *
 * Within each group, and between consignors, the oldest receipt leaves first.
 */
enum IssueOrder: string
{
    /** The shop's own stock first: its money comes back before it sells for others. */
    case OwnedFirst = 'owned_first';
    /** The consigned stock first: what the shop does not own leaves before what it paid for. */
    case ConsignedFirst = 'consigned_first';
    /** The oldest receipt first, whoever owns it. */
    case OldestFirst = 'oldest_first';

    /**
     * The layers in the order their units leave.
     *
     * @param list<StockLayer> $layers
     *
     * @return list<StockLayer>
     */
    public function sort(array $layers): array
    {
        usort($layers, function (StockLayer $left, StockLayer $right): int {
            $byGroup = match ($this) {
                self::OwnedFirst => $left->owner()->isConsigned() <=> $right->owner()->isConsigned(),
                self::ConsignedFirst => $right->owner()->isConsigned() <=> $left->owner()->isConsigned(),
                self::OldestFirst => 0,
            };

            return 0 !== $byGroup ? $byGroup : self::age($left, $right);
        });

        return $layers;
    }

    /**
     * Oldest receipt first; between layers received at the same instant, the receipt layer written first.
     */
    public static function age(StockLayer $left, StockLayer $right): int
    {
        $byInstant = $left->receivedAt() <=> $right->receivedAt();

        return 0 !== $byInstant ? $byInstant : [$left->origin, $left->id] <=> [$right->origin, $right->id];
    }
}
