<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Application\Sales;

use Phil\CommerceReportsBundle\Application\Contract\ReturnedLines;
use Phil\CommerceReportsBundle\Application\Contract\SoldLines;
use Phil\CommerceReportsBundle\Domain\Sales\SalesDimension;
use Phil\Money\Currency;
use Phil\ReportingBundle\Application\Exception\ReportTooLarge;
use Phil\ReportingBundle\Domain\ValueObject\Period;

/**
 * The lines sold and returned in a period, totalled by a dimension and by currency, in one pass over each port: the
 * totals are held in memory, never the lines.
 */
final readonly class SalesLedger
{
    /** The most totals a period gives: one per value of the dimension and per currency. */
    public const int MAX_TOTALS = 50_000;

    public function __construct(
        private SoldLines $sold,
        private ReturnedLines $returned,
        private int $maxTotals = self::MAX_TOTALS,
    ) {
    }

    /**
     * The totals, by value of the dimension, then by currency; the lines without a value under an empty key.
     *
     * @return list<SalesTotals>
     *
     * @throws ReportTooLarge when there are more totals than the bound, 50 000 by default
     */
    public function totals(string $report, Period $period, SalesDimension $dimension): array
    {
        /** @var array<string, SalesTotals> $totals */
        $totals = [];

        foreach ($this->sold->between($period) as $line) {
            $key = $dimension->of($line) ?? '';
            $id = $key."\0".$line->gross->currency->code;
            $totals[$id] = ($totals[$id] ?? $this->start($report, \count($totals), $key, $dimension->label($line), $line->gross->currency))
                ->withSale($line->quantity, $line->gross, $line->discounts);
        }

        foreach ($this->returned->between($period) as $line) {
            $key = $dimension->of($line) ?? '';
            $id = $key."\0".$line->amount->currency->code;
            $totals[$id] = ($totals[$id] ?? $this->start($report, \count($totals), $key, $dimension->label($line), $line->amount->currency))
                ->withReturn($line->quantity, $line->amount);
        }

        ksort($totals, \SORT_STRING);

        return array_values($totals);
    }

    private function start(string $report, int $count, string $key, string $label, Currency $currency): SalesTotals
    {
        if ($count >= $this->maxTotals) {
            throw ReportTooLarge::of($report, $this->maxTotals);
        }

        return SalesTotals::empty($key, $label, $currency);
    }
}
