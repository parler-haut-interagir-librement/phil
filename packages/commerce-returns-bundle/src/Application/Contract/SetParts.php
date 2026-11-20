<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application\Contract;

use Phil\CommerceBundle\Domain\ValueObject\SellableReference;

/**
 * Tells which order lines are sets — a box assembled once and sold as one product — and what they hold, so that a
 * request for one of their parts is refused: a set comes back whole. `Infrastructure\Catalog\CatalogSetParts` reads
 * the composite products of `phil/commerce-catalog-bundle`.
 */
interface SetParts
{
    /**
     * The references of the parts one unit of a line of this sellable holds, as order lines would name them; none for
     * what is no set.
     *
     * @return list<string> `type:id`
     */
    public function partsOf(SellableReference $reference): array;
}
