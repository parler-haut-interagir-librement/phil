<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\Subscription;

/**
 * What a customer asked to be told: that a SKU is back in stock, or that its price fell to their threshold.
 */
enum SubscriptionKind: string
{
    case BackInStock = 'back_in_stock';
    case PriceDrop = 'price_drop';
}
