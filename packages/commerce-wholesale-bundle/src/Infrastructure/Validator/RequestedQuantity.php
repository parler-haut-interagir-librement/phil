<?php

declare(strict_types=1);

namespace Phil\CommerceWholesaleBundle\Infrastructure\Validator;

/**
 * What an `AllowedQuantity` checks: how many units of a SKU a line would hold, for a buyer of a customer group or of
 * none. An application's form object returns one from a getter carrying the constraint.
 */
final readonly class RequestedQuantity
{
    public function __construct(
        public string $sku,
        public int $quantity,
        public ?string $customerGroup = null,
    ) {
    }
}
