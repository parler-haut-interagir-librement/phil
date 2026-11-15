<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Application;

use DateTimeImmutable;
use Override;
use Phil\CommerceBundle\Application\Contract\OrderShipments;
use Phil\CommerceBundle\Domain\ValueObject\SellableReference;

/**
 * The bundle's `OrderShipments` when no logistics module replaces it: no order is known to have left.
 */
final readonly class UnknownShipments implements OrderShipments
{
    #[Override]
    public function shippedAt(string $orderNumber, ?SellableReference $line = null): ?DateTimeImmutable
    {
        return null;
    }
}
