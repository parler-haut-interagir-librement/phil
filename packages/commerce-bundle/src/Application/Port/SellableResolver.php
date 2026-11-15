<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application\Port;

use Phil\CommerceBundle\Domain\ValueObject\Sellable;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;

/**
 * Tells the core what a reference sells. Each module that sells something implements it for its own types: the
 * catalogue for products, subscriptions for offers, an application for whatever else it sells.
 *
 * A service implementing it is tagged `phil_commerce.sellable_resolver` by autoconfiguration.
 */
interface SellableResolver
{
    /**
     * Whether the resolver answers for this type of reference; exactly one resolver should.
     */
    public function supports(string $type): bool;

    /**
     * What the reference sells, or `null` when nothing is known by its identifier.
     */
    public function resolve(SellableReference $reference): ?Sellable;
}
