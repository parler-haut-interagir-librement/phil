<?php

declare(strict_types=1);

namespace Phil\CommercePromotionsBundle\Application\Contract;

/**
 * Says whether a customer owns a product: bought, received or unlocked, as the application keeps it. A promotion that
 * lists owned products applies only to a customer who owns one of them; without an implementation, it never applies.
 */
interface CustomerOwnership
{
    /**
     * @param non-empty-list<string> $skus
     */
    public function ownsAny(string $customer, array $skus): bool;
}
