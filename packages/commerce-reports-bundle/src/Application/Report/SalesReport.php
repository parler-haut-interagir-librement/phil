<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Application\Report;

use Override;
use Phil\CommerceReportsBundle\Application\Sales\SalesLedger;
use Phil\CommerceReportsBundle\Domain\Sales\SalesDimension;
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Application\Report\ReportRequest;

/**
 * The gross and net sales of the period, by product, category, channel, location or cashier, one row per value and per
 * currency.
 *
 * Its parameter `by` — `product` by default, `category`, `channel`, `location` or `cashier` — names the dimension. The
 * amounts are decimals in their currency, never converted: two currencies are two rows.
 */
final readonly class SalesReport implements ReportDefinition
{
    public function __construct(
        private SalesLedger $ledger,
    ) {
    }

    #[Override]
    public static function key(): string
    {
        return 'commerce_sales';
    }

    #[Override]
    public function title(): string
    {
        return 'Gross and net sales';
    }

    #[Override]
    public function columns(ReportRequest $request): array
    {
        $dimension = $this->dimension($request);

        return [
            $dimension->heading(),
            SalesDimension::Product === $dimension ? 'Product' : 'Name',
            'Currency',
            'Units sold',
            'Units returned',
            'Gross sales',
            'Discounts',
            'Returns',
            'Net sales',
        ];
    }

    #[Override]
    public function rows(ReportRequest $request): iterable
    {
        foreach ($this->ledger->totals(self::key(), $request->period, $this->dimension($request)) as $totals) {
            yield [
                $totals->key,
                $totals->label,
                $totals->currency->code,
                $totals->sold,
                $totals->returned,
                $totals->gross->toDecimal(),
                $totals->discounts->toDecimal(),
                $totals->returns->toDecimal(),
                $totals->net()->toDecimal(),
            ];
        }
    }

    /**
     * @throws InvalidReportJob when `by` names no dimension
     */
    private function dimension(ReportRequest $request): SalesDimension
    {
        $by = $request->parameter('by', SalesDimension::Product->value);
        $dimension = \is_string($by) ? SalesDimension::tryFrom($by) : null;

        if (null === $dimension) {
            throw InvalidReportJob::because('the parameter "by" is product, category, channel, location or cashier');
        }

        return $dimension;
    }
}
