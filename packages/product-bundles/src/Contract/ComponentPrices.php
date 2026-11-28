<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Contract;

use Phil\Money\Currency;
use Phil\Money\Money;

/**
 * What one unit of a component sells for on its own, in a currency: the catalogue's price for the channel.
 */
interface ComponentPrices
{
    /**
     * The unit price, null when the SKU has no price in that currency.
     */
    public function unitPrice(string $sku, Currency $currency): ?Money;
}
