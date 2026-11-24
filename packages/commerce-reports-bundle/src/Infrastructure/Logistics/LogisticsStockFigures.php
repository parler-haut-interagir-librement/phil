<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Infrastructure\Logistics;

use DateTimeImmutable;
use Override;
use Phil\CommerceLogisticsBundle\Domain\Repository\StockRepository;
use Phil\CommerceLogisticsBundle\Domain\Stock\MovementKind;
use Phil\CommerceReportsBundle\Application\Contract\StockFigures;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockFigure;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The stock figures of the logistics module, read through its `StockRepository`: the SKU's levels at every location,
 * and the journal of each level.
 *
 * - the starting stock is the sum of the on-hand changes recorded before the period starts;
 * - the units received are the on-hand units of the `received` moves inside the period; a transfer between two
 *   locations moves no unit of the SKU as a whole and is not counted;
 * - the units available are the levels' available units now, on hand less reserved;
 * - the last sale is the latest `shipped` move that took units off the shelf, at any moment.
 *
 * The journal is read whole for each location: the cost of a SKU grows with its moves.
 */
final readonly class LogisticsStockFigures implements StockFigures
{
    public function __construct(
        private StockRepository $stock,
    ) {
    }

    #[Override]
    public function of(string $sku, Period $period): StockFigure
    {
        $start = 0;
        $received = 0;
        $available = 0;
        $lastSoldAt = null;

        foreach ($this->stock->levelsOf($sku) as $level) {
            $available += $level->available();

            foreach ($this->stock->journal($sku, $level->location) as $move) {
                if ($move->movedAt < $period->start) {
                    $start += $move->onHandChange;
                } elseif (MovementKind::Received === $move->kind && $period->contains($move->movedAt)) {
                    $received += $move->onHandChange;
                }

                if (MovementKind::Shipped === $move->kind && $move->onHandChange < 0) {
                    $lastSoldAt = $this->later($lastSoldAt, $move->movedAt);
                }
            }
        }

        return new StockFigure($sku, $start, max(0, $received), $available, $lastSoldAt);
    }

    private function later(?DateTimeImmutable $known, DateTimeImmutable $moment): DateTimeImmutable
    {
        return $known instanceof DateTimeImmutable && $known > $moment ? $known : $moment;
    }
}
