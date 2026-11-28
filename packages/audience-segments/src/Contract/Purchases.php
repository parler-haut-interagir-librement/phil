<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Contract;

use Phil\AudienceSegments\PurchaseFilter;

/**
 * What the shop of an application knows of a member's orders.
 */
interface Purchases
{
    /**
     * Whether the member has an order that matches every part of the filter that is given.
     */
    public function bought(string $member, PurchaseFilter $filter): bool;
}
