<?php

declare(strict_types=1);

namespace Phil\InventoryAlerts\LowStock;

/**
 * What the staff is told about a SKU at a location: at or under its threshold, or with nothing left to sell.
 */
enum LowStockSituation: string
{
    case Low = 'low';
    case Out = 'out';

    /**
     * The situation of a number of available units against a threshold, or null when the stock is above it.
     */
    public static function of(int $available, int $threshold): ?self
    {
        return match (true) {
            $available <= 0 => self::Out,
            $available <= $threshold => self::Low,
            default => null,
        };
    }
}
