<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Application\Report;

use Override;
use Phil\CommerceReportsBundle\Application\Contract\StockedSkus;
use Phil\CommerceReportsBundle\Application\Contract\StockFigures;
use Phil\CommerceReportsBundle\Domain\Formula\Dormancy;
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Psr\Clock\ClockInterface;

/**
 * The dead stock: every stocked SKU with units available that has not sold for a number of days, or never sold,
 * counted from now on the clock.
 *
 * Its parameter `days` — 90 by default, or the bundle's `dead_stock_days` — is the number of days without a sale, from
 * 1 to 3650. The period of the request bounds the units received and the starting stock the stock port reads; the age
 * of the last sale is counted from now.
 */
final readonly class DeadStockReport implements ReportDefinition
{
    public function __construct(
        private StockFigures $stock,
        private StockedSkus $skus,
        private ClockInterface $clock,
        private int $days = Dormancy::DEFAULT_DAYS,
    ) {
    }

    #[Override]
    public static function key(): string
    {
        return 'commerce_dead_stock';
    }

    #[Override]
    public function title(): string
    {
        return 'Dead stock';
    }

    #[Override]
    public function columns(ReportRequest $request): array
    {
        return ['SKU', 'Product', 'Category', 'Units available', 'Last sale', 'Days without a sale'];
    }

    #[Override]
    public function rows(ReportRequest $request): iterable
    {
        $days = $this->days($request);
        $now = $this->clock->now();

        foreach ($this->skus->all() as $sku) {
            $figure = $this->stock->of($sku->sku, $request->period);

            if (!Dormancy::isDormant($figure, $now, $days)) {
                continue;
            }

            yield [
                $sku->sku,
                $sku->product,
                $sku->category ?? '',
                $figure->available,
                $figure->lastSoldAt?->format('Y-m-d') ?? '',
                Dormancy::daysWithoutSale($figure->lastSoldAt, $now),
            ];
        }
    }

    /**
     * @throws InvalidReportJob when `days` is not a whole number from 1 to 3650
     */
    private function days(ReportRequest $request): int
    {
        $days = $request->parameter('days', $this->days);

        if (\is_string($days) && 1 === preg_match('/^\d{1,4}$/D', $days)) {
            $days = (int) $days;
        }

        if (!\is_int($days) || $days < 1 || $days > Dormancy::MAX_DAYS) {
            throw InvalidReportJob::because(\sprintf('the parameter "days" is a whole number from 1 to %d', Dormancy::MAX_DAYS));
        }

        return $days;
    }
}
