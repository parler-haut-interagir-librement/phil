<?php

declare(strict_types=1);

namespace Phil\CommerceReturnsBundle\Application\Port;

use DateTimeImmutable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;

/**
 * Puts back on the shelf what came back resellable: a stock movement of `phil/commerce-logistics-bundle`, or the
 * application's own.
 *
 * A service implementing it is tagged `phil_commerce_returns.restocking` by autoconfiguration; every one runs when a
 * return is received, in the transaction that records it.
 */
interface Restocking
{
    /**
     * @param SellableReference $line the reference of the order line, with the fingerprint of its configuration for a
     *                                configured line; what goes back on the shelf is its `unconfigured()` sellable
     */
    public function restock(SellableReference $line, int $quantity, string $return, DateTimeImmutable $at): void;
}
