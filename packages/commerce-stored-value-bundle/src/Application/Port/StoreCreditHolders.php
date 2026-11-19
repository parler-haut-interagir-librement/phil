<?php

declare(strict_types=1);

namespace Phil\CommerceStoredValueBundle\Application\Port;

/**
 * Who an order's customer is, for store credit: the module knows a gift card by its code, not by whoever holds it.
 *
 * `StoredValueGateway` asks it when a refund cannot go back to the card it was spent from — the card was voided, or
 * has expired — and grants the amount as that customer's store credit instead. The application implements it; without
 * it, such a refund is refused.
 */
interface StoreCreditHolders
{
    /**
     * @return string|null the customer, named as `StoreCredits` names it; `null` when the order has none known
     */
    public function holderOf(string $orderNumber): ?string;
}
