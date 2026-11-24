<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Application\Report;

use Override;
use Phil\CommerceReportsBundle\Application\Contract\SoldLines;
use Phil\CommerceReportsBundle\Application\Contract\StockedSkus;
use Phil\CommerceReportsBundle\Application\Contract\StockFigures;
use Phil\CommerceReportsBundle\Domain\Formula\SellThrough;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockedSku;
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Application\Exception\ReportTooLarge;
use Phil\ReportingBundle\Application\Report\ReportRequest;

/**
 * The sell-through of the period, by SKU or by category: the units sold over the units on hand at the start plus the
 * units received, in parts per million and as a percentage.
 *
 * Its parameter `by` — `sku` by default, or `category` — names the rows. The SKUs are those stocked and those sold in
 * the period; a SKU with nothing to sell from has an empty sell-through.
 */
final readonly class SellThroughReport implements ReportDefinition
{
    /** The most SKUs a period reads, stocked or sold. */
    public const int MAX_SKUS = 100_000;

    public function __construct(
        private SoldLines $sold,
        private StockFigures $stock,
        private StockedSkus $skus,
        private int $maxSkus = self::MAX_SKUS,
    ) {
    }

    #[Override]
    public static function key(): string
    {
        return 'commerce_sell_through';
    }

    #[Override]
    public function title(): string
    {
        return 'Sell-through';
    }

    #[Override]
    public function columns(ReportRequest $request): array
    {
        return [
            $this->byCategory($request) ? 'Category' : 'SKU',
            $this->byCategory($request) ? 'SKUs' : 'Product',
            'Starting stock',
            'Units received',
            'Units sold',
            'Sell-through (ppm)',
            'Sell-through (%)',
        ];
    }

    #[Override]
    public function rows(ReportRequest $request): iterable
    {
        $byCategory = $this->byCategory($request);
        /** @var array<string, array{StockedSku, int}> $skus */
        $skus = [];

        foreach ($this->skus->all() as $sku) {
            $skus[$sku->sku] = [$sku, 0];
            $this->bound(\count($skus));
        }

        foreach ($this->sold->between($request->period) as $line) {
            // StockedSkus::all() lists every stocked SKU: one sold but not listed is named by its line.
            $skus[$line->sku] ??= [new StockedSku($line->sku, $line->product, $line->category), 0];
            $skus[$line->sku][1] += $line->quantity;
            $this->bound(\count($skus));
        }

        ksort($skus, \SORT_STRING);
        /** @var array<string, array{string, int, int, int, int}> $categories */
        $categories = [];

        foreach ($skus as [$sku, $sold]) {
            $figure = $this->stock->of($sku->sku, $request->period);

            if (!$byCategory) {
                yield [$sku->sku, $sku->product, $figure->start, $figure->received, $sold, ...$this->sellThrough($sold, $figure->start, $figure->received)];

                continue;
            }

            $category = $sku->category ?? '';
            [, $count, $start, $received, $units] = $categories[$category] ?? [$category, 0, 0, 0, 0];
            $categories[$category] = [$category, $count + 1, $start + $figure->start, $received + $figure->received, $units + $sold];
        }

        ksort($categories, \SORT_STRING);

        foreach ($categories as [$category, $count, $start, $received, $units]) {
            yield [$category, $count, $start, $received, $units, ...$this->sellThrough($units, $start, $received)];
        }
    }

    /**
     * @return array{int|null, float|null}
     */
    private function sellThrough(int $sold, int $start, int $received): array
    {
        $ppm = SellThrough::ppm($sold, $start, $received);

        return [$ppm, null === $ppm ? null : fdiv($ppm, 10_000)];
    }

    /**
     * @throws InvalidReportJob when `by` is neither `sku` nor `category`
     */
    private function byCategory(ReportRequest $request): bool
    {
        return match ($request->parameter('by', 'sku')) {
            'sku' => false,
            'category' => true,
            default => throw InvalidReportJob::because('the parameter "by" is sku or category'),
        };
    }

    /**
     * @throws ReportTooLarge when there are more SKUs than the bound, 100 000 by default
     */
    private function bound(int $count): void
    {
        if ($count > $this->maxSkus) {
            throw ReportTooLarge::of(self::key(), $this->maxSkus);
        }
    }
}
