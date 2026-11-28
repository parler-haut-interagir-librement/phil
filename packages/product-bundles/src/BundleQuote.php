<?php

declare(strict_types=1);

namespace Phil\ProductBundles;

use Phil\Money\Money;

/**
 * The price of one bundle now: what its components sell for apart, what the bundle sells for, and each component's
 * share of that price, the shares adding up to the price to the unit.
 */
final readonly class BundleQuote
{
    /**
     * @param list<QuotedComponent> $components in the bundle's order
     */
    public function __construct(
        public string $sku,
        public Money $componentsValue,
        public Money $price,
        public array $components,
    ) {
    }

    /**
     * What the buyer saves over buying the components apart; negative when the bundle sells above them.
     */
    public function savings(): Money
    {
        return $this->componentsValue->subtract($this->price);
    }
}
