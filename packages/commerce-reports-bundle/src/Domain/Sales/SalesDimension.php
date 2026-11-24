<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Domain\Sales;

use Phil\CommerceReportsBundle\Domain\ValueObject\ReturnedLine;
use Phil\CommerceReportsBundle\Domain\ValueObject\SoldLine;

/**
 * What the sales are totalled by: a product, named by its SKU; a category; a channel; a location; a cashier.
 */
enum SalesDimension: string
{
    case Product = 'product';
    case Category = 'category';
    case Channel = 'channel';
    case Location = 'location';
    case Cashier = 'cashier';

    /**
     * The value of the line along the dimension, null when the line has none.
     */
    public function of(SoldLine|ReturnedLine $line): ?string
    {
        return match ($this) {
            self::Product => $line->sku,
            self::Category => $line->category,
            self::Channel => $line->channel,
            self::Location => $line->location,
            self::Cashier => $line->cashier,
        };
    }

    /**
     * The name a row shows beside the value: the product's name, or the value itself.
     */
    public function label(SoldLine|ReturnedLine $line): string
    {
        return self::Product === $this ? $line->product : ($this->of($line) ?? '');
    }

    /**
     * The heading of the column of values.
     */
    public function heading(): string
    {
        return match ($this) {
            self::Product => 'SKU',
            self::Category => 'Category',
            self::Channel => 'Channel',
            self::Location => 'Location',
            self::Cashier => 'Cashier',
        };
    }
}
