<?php

declare(strict_types=1);

namespace Phil\CommerceConsignmentBundle\Domain\ValueObject;

use Phil\Money\Money;

/**
 * Units of one layer a return slip gave back, or the shop bought: for a buy-out, the shop's layer they reached and what
 * the consignor is owed for them.
 */
final readonly class SlipLine
{
    public function __construct(
        public string $layer,
        public string $sku,
        public int $quantity,
        public ?string $shopLayer = null,
        public ?Money $owed = null,
    ) {
    }
}
