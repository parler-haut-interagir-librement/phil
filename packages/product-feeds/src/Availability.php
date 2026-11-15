<?php

declare(strict_types=1);

namespace Phil\ProductFeeds;

/**
 * Whether an item can be bought now, in the words of the product data specification comparison sites read.
 */
enum Availability: string
{
    case InStock = 'in_stock';
    case OutOfStock = 'out_of_stock';
    case Preorder = 'preorder';
    case Backorder = 'backorder';

    /**
     * Whether a date says when it can be bought: a preorder ships, a backorder comes back in stock.
     */
    public function takesADate(): bool
    {
        return self::Preorder === $this || self::Backorder === $this;
    }
}
