<?php

declare(strict_types=1);

namespace Phil\Pricing;

/**
 * Where a suggested retail price comes from.
 */
enum SuggestedRetailPriceSource: string
{
    /** Given by the supplier or the manufacturer, as printed on its price list. */
    case Supplier = 'supplier';

    /** Computed from the cost by a markup; keystone is a markup of 100 %. */
    case CostMarkup = 'cost_markup';

    /** Computed from a wholesale price by the retailer's markup. */
    case WholesaleMarkup = 'wholesale_markup';
}
