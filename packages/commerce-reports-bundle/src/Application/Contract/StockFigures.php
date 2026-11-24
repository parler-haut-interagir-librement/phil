<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Application\Contract;

use Phil\CommerceReportsBundle\Domain\ValueObject\StockFigure;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The stock of a SKU: `LogisticsStockFigures` reads the stock journal of the logistics module.
 */
interface StockFigures
{
    /**
     * The SKU's units on hand at the period's start, received during it, available now, and its last sale; zeros and
     * no sale for a SKU the module does not know.
     */
    public function of(string $sku, Period $period): StockFigure;
}
