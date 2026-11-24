<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Unit\Application;

use Phil\CommerceReportsBundle\Application\Sales\SalesLedger;
use Phil\CommerceReportsBundle\Application\Sales\SalesTotals;
use Phil\CommerceReportsBundle\Domain\Sales\SalesDimension;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryReturnedLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemorySoldLines;
use Phil\CommerceReportsBundle\Tests\Fixture\Lines;
use Phil\ReportingBundle\Application\Exception\ReportTooLarge;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SalesLedger::class)]
#[CoversClass(SalesTotals::class)]
#[CoversClass(SalesDimension::class)]
final class SalesLedgerTest extends TestCase
{
    public function testTheSalesAreTotalledByProductAndCurrency(): void
    {
        $ledger = new SalesLedger(
            new InMemorySoldLines([
                Lines::sold('B', 2, 2000, 300),
                Lines::sold('A', 1, 1000, 0),
                Lines::sold('B', 1, 1000, 100),
                Lines::sold('B', 1, 1500, 0, 'USD'),
            ]),
            new InMemoryReturnedLines([Lines::returned('B', 1, 900)]),
        );

        $totals = $ledger->totals('report', Lines::period(), SalesDimension::Product);

        self::assertSame(
            [
                ['A', 'Product A', 'CAD', 1, 0, '10.00', '0.00', '0.00', '10.00'],
                ['B', 'Product B', 'CAD', 3, 1, '30.00', '4.00', '9.00', '17.00'],
                ['B', 'Product B', 'USD', 1, 0, '15.00', '0.00', '0.00', '15.00'],
            ],
            $this->rows($totals),
        );
    }

    public function testReturnsLargerThanTheSalesGiveNegativeNetSales(): void
    {
        $ledger = new SalesLedger(
            new InMemorySoldLines([Lines::sold('A', 1, 1000, 200)]),
            new InMemoryReturnedLines([Lines::returned('A', 3, 3000), Lines::returned('C', 1, 500, 'EUR')]),
        );

        self::assertSame(
            [
                ['A', 'Product A', 'CAD', 1, 3, '10.00', '2.00', '30.00', '-22.00'],
                ['C', 'Product C', 'EUR', 0, 1, '0.00', '0.00', '5.00', '-5.00'],
            ],
            $this->rows($ledger->totals('report', Lines::period(), SalesDimension::Product)),
        );
    }

    public function testLinesWithoutAValueAreTotalledTogether(): void
    {
        $ledger = new SalesLedger(
            new InMemorySoldLines([Lines::sold('A', cashier: null), Lines::sold('B', cashier: 'bob'), Lines::sold('C', cashier: null)]),
            new InMemoryReturnedLines([Lines::returned('D', cashier: null)]),
        );

        self::assertSame(
            [
                ['', '', 'CAD', 2, 1, '20.00', '0.00', '10.00', '10.00'],
                ['bob', 'bob', 'CAD', 1, 0, '10.00', '0.00', '0.00', '10.00'],
            ],
            $this->rows($ledger->totals('report', Lines::period(), SalesDimension::Cashier)),
        );
    }

    public function testTheDimensionsReadTheirValue(): void
    {
        $line = Lines::sold('A', category: 'books', channel: 'web', location: 'north', cashier: 'ann');
        $returned = Lines::returned('A', category: 'games', channel: 'store', location: 'south', cashier: 'bob');

        self::assertSame(['A', 'books', 'web', 'north', 'ann'], array_map(static fn (SalesDimension $dimension): ?string => $dimension->of($line), SalesDimension::cases()));
        self::assertSame(['A', 'games', 'store', 'south', 'bob'], array_map(static fn (SalesDimension $dimension): ?string => $dimension->of($returned), SalesDimension::cases()));
        self::assertSame(['Product A', 'books', 'web', 'north', 'ann'], array_map(static fn (SalesDimension $dimension): string => $dimension->label($line), SalesDimension::cases()));
        self::assertSame(['SKU', 'Category', 'Channel', 'Location', 'Cashier'], array_map(static fn (SalesDimension $dimension): string => $dimension->heading(), SalesDimension::cases()));
        self::assertSame('', SalesDimension::Category->label(Lines::sold(category: null)));
    }

    public function testTheTotalsAreBoundedOnTheSales(): void
    {
        $lines = [Lines::sold('A'), Lines::sold('B')];
        $ledger = new SalesLedger(new InMemorySoldLines($lines), new InMemoryReturnedLines(), 2);

        self::assertCount(2, $ledger->totals('report', Lines::period(), SalesDimension::Product));

        $this->expectException(ReportTooLarge::class);
        $this->expectExceptionMessageIsOrContains('The report "report" has more than 1 rows.');

        new SalesLedger(new InMemorySoldLines($lines), new InMemoryReturnedLines(), 1)->totals('report', Lines::period(), SalesDimension::Product);
    }

    public function testTheTotalsAreBoundedOnTheReturns(): void
    {
        $ledger = new SalesLedger(new InMemorySoldLines([Lines::sold('A')]), new InMemoryReturnedLines([Lines::returned('A'), Lines::returned('B')]), 2);

        self::assertCount(2, $ledger->totals('report', Lines::period(), SalesDimension::Product));

        $this->expectException(ReportTooLarge::class);

        new SalesLedger(new InMemorySoldLines([Lines::sold('A')]), new InMemoryReturnedLines([Lines::returned('B')]), 1)->totals('report', Lines::period(), SalesDimension::Product);
    }

    public function testTheDefaultBound(): void
    {
        self::assertSame(50_000, SalesLedger::MAX_TOTALS);
    }

    /**
     * @param list<SalesTotals> $totals
     *
     * @return list<list<int|string>>
     */
    private function rows(array $totals): array
    {
        return array_map(static fn (SalesTotals $total): array => [
            $total->key,
            $total->label,
            $total->currency->code,
            $total->sold,
            $total->returned,
            $total->gross->toDecimal(),
            $total->discounts->toDecimal(),
            $total->returns->toDecimal(),
            $total->net()->toDecimal(),
        ], $totals);
    }
}
