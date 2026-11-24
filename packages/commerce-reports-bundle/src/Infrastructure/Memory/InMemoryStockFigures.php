<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Infrastructure\Memory;

use Override;
use Phil\CommerceReportsBundle\Application\Contract\StockFigures;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockFigure;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * Stock figures given once, for tests and for an application without the logistics module: the same figure of a SKU
 * whatever the period, and zeros with no sale for a SKU it was not given.
 */
final readonly class InMemoryStockFigures implements StockFigures
{
    /** @var array<string, StockFigure> */
    private array $figures;

    /**
     * @param list<StockFigure> $figures
     */
    public function __construct(array $figures = [])
    {
        $bySku = [];

        foreach ($figures as $figure) {
            $bySku[$figure->sku] = $figure;
        }

        $this->figures = $bySku;
    }

    #[Override]
    public function of(string $sku, Period $period): StockFigure
    {
        return $this->figures[$sku] ?? new StockFigure($sku, 0, 0, 0, null);
    }
}
