<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\CommerceReportsBundle\Application\Report\DeadStockReport;
use Phil\CommerceReportsBundle\Application\Report\SalesReport;
use Phil\CommerceReportsBundle\Application\Report\SellThroughReport;
use Phil\CommerceReportsBundle\Application\Sales\SalesLedger;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockedSku;
use Phil\CommerceReportsBundle\Domain\ValueObject\StockFigure;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryReturnedLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemorySoldLines;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockedSkus;
use Phil\CommerceReportsBundle\Infrastructure\Memory\InMemoryStockFigures;
use Phil\CommerceReportsBundle\Tests\Fixture\Lines;
use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Application\Exception\ReportTooLarge;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(SalesReport::class)]
#[CoversClass(SellThroughReport::class)]
#[CoversClass(DeadStockReport::class)]
final class ReportsTest extends TestCase
{
    public function testTheSalesReportTotalsByProductByDefault(): void
    {
        $report = $this->sales();
        $request = new ReportRequest(Lines::period());

        self::assertSame('commerce_sales', SalesReport::key());
        self::assertSame('Gross and net sales', $report->title());
        self::assertSame(['SKU', 'Product', 'Currency', 'Units sold', 'Units returned', 'Gross sales', 'Discounts', 'Returns', 'Net sales'], $report->columns($request));
        self::assertSame(
            [
                ['A', 'Product A', 'CAD', 2, 1, '20.00', '1.50', '8.00', '10.50'],
                ['B', 'Product B', 'USD', 1, 0, '5.00', '0.00', '0.00', '5.00'],
            ],
            iterator_to_array($report->rows($request), false),
        );
    }

    public function testTheSalesReportTotalsByAnotherDimension(): void
    {
        $report = $this->sales();
        $request = new ReportRequest(Lines::period(), ['by' => 'channel']);

        self::assertSame(['Channel', 'Name', 'Currency', 'Units sold', 'Units returned', 'Gross sales', 'Discounts', 'Returns', 'Net sales'], $report->columns($request));
        self::assertSame(
            [
                ['store', 'store', 'CAD', 1, 1, '10.00', '1.50', '8.00', '0.50'],
                ['web', 'web', 'CAD', 1, 0, '10.00', '0.00', '0.00', '10.00'],
                ['web', 'web', 'USD', 1, 0, '5.00', '0.00', '0.00', '5.00'],
            ],
            iterator_to_array($report->rows($request), false),
        );
    }

    /**
     * @return iterable<string, array{bool|float|int|string}>
     */
    public static function wrongDimensions(): iterable
    {
        yield 'an unknown dimension' => ['colour'];
        yield 'a number' => [1];
    }

    #[DataProvider('wrongDimensions')]
    public function testTheSalesReportRefusesAnUnknownDimension(bool|float|int|string $by): void
    {
        $this->expectException(InvalidReportJob::class);
        $this->expectExceptionMessageIsOrContains('the parameter "by" is product, category, channel, location or cashier');

        $this->sales()->columns(new ReportRequest(Lines::period(), ['by' => $by]));
    }

    public function testTheSellThroughBySku(): void
    {
        $report = $this->sellThrough();
        $request = new ReportRequest(Lines::period());

        self::assertSame('commerce_sell_through', SellThroughReport::key());
        self::assertSame('Sell-through', $report->title());
        self::assertSame(['SKU', 'Product', 'Starting stock', 'Units received', 'Units sold', 'Sell-through (ppm)', 'Sell-through (%)'], $report->columns($request));
        self::assertSame(
            [
                ['A', 'Apple', 2, 1, 2, 666_667, 66.6667],
                ['B', 'Banana', 0, 0, 0, null, null],
                ['C', 'Product C', 4, 0, 1, 250_000, 25.0],
                ['D', 'Date', 8, 0, 1, 125_000, 12.5],
            ],
            iterator_to_array($report->rows($request), false),
        );
    }

    public function testTheSellThroughByCategory(): void
    {
        $report = $this->sellThrough();
        $request = new ReportRequest(Lines::period(), ['by' => 'category']);

        self::assertSame(['Category', 'SKUs', 'Starting stock', 'Units received', 'Units sold', 'Sell-through (ppm)', 'Sell-through (%)'], $report->columns($request));
        self::assertSame(
            [
                ['', 1, 0, 0, 0, null, null],
                ['2026', 1, 4, 0, 1, 250_000, 25.0],
                ['fruit', 2, 10, 1, 3, 272_727, 27.2727],
            ],
            iterator_to_array($report->rows($request), false),
        );
    }

    public function testTheSellThroughRefusesAnUnknownGrouping(): void
    {
        $this->expectException(InvalidReportJob::class);
        $this->expectExceptionMessageIsOrContains('the parameter "by" is sku or category');

        $this->sellThrough()->columns(new ReportRequest(Lines::period(), ['by' => 'product']));
    }

    public function testTheSellThroughIsBoundedOnTheStockedSkus(): void
    {
        $skus = new InMemoryStockedSkus([new StockedSku('A', 'A'), new StockedSku('B', 'B')]);
        $request = new ReportRequest(Lines::period());

        self::assertCount(2, iterator_to_array(new SellThroughReport(new InMemorySoldLines(), new InMemoryStockFigures(), $skus, 2)->rows($request), false));

        $this->expectException(ReportTooLarge::class);
        $this->expectExceptionMessageIsOrContains('The report "commerce_sell_through" has more than 1 rows.');

        iterator_to_array(new SellThroughReport(new InMemorySoldLines(), new InMemoryStockFigures(), $skus, 1)->rows($request), false);
    }

    public function testTheSellThroughIsBoundedOnTheSkusSold(): void
    {
        $sold = new InMemorySoldLines([Lines::sold('A'), Lines::sold('B')]);
        $request = new ReportRequest(Lines::period());

        self::assertCount(2, iterator_to_array(new SellThroughReport($sold, new InMemoryStockFigures(), new InMemoryStockedSkus(), 2)->rows($request), false));

        $this->expectException(ReportTooLarge::class);

        iterator_to_array(new SellThroughReport($sold, new InMemoryStockFigures(), new InMemoryStockedSkus(), 1)->rows($request), false);
    }

    public function testTheSellThroughDefaultBound(): void
    {
        self::assertSame(100_000, SellThroughReport::MAX_SKUS);
    }

    public function testTheDeadStock(): void
    {
        $report = $this->deadStock();
        $request = new ReportRequest(Lines::period());

        self::assertSame('commerce_dead_stock', DeadStockReport::key());
        self::assertSame('Dead stock', $report->title());
        self::assertSame(['SKU', 'Product', 'Category', 'Units available', 'Last sale', 'Days without a sale'], $report->columns($request));
        self::assertSame(
            [
                ['OLD', 'Old book', 'books', 3, '2026-06-01', 172],
                ['NEW', 'New book', '', 1, '', null],
            ],
            iterator_to_array($report->rows($request), false),
        );
    }

    /**
     * @return iterable<string, array{bool|float|int|string, list<string>}>
     */
    public static function thresholds(): iterable
    {
        yield 'a number of days' => [30, ['OLD', 'NEW']];
        yield 'a number of days written as text' => ['30', ['OLD', 'NEW']];
        yield 'one day' => [1, ['RECENT', 'OLD', 'NEW']];
        yield 'ten years' => [3650, ['NEW']];
        yield 'ten years written as text' => ['3650', ['NEW']];
    }

    /**
     * @param list<string> $skus
     */
    #[DataProvider('thresholds')]
    public function testTheDeadStockAfterOtherDays(bool|float|int|string $days, array $skus): void
    {
        $rows = iterator_to_array($this->deadStock()->rows(new ReportRequest(Lines::period(), ['days' => $days])), false);

        self::assertSame($skus, array_column($rows, 0));
    }

    public function testTheDeadStockAfterTheConfiguredDays(): void
    {
        $rows = iterator_to_array($this->deadStock(200)->rows(new ReportRequest(Lines::period())), false);

        self::assertSame(['NEW'], array_column($rows, 0));
    }

    /**
     * @return iterable<string, array{bool|float|int|string}>
     */
    public static function wrongThresholds(): iterable
    {
        yield 'no day' => [0];
        yield 'no day written as text' => ['0'];
        yield 'more than ten years' => [3651];
        yield 'more than ten years written as text' => ['3651'];
        yield 'a fraction' => [1.5];
        yield 'a word' => ['week'];
        yield 'a number followed by a word' => ['30 days'];
        yield 'a negative number written as text' => ['-5'];
        yield 'five digits' => ['00030'];
        yield 'a number followed by a line break' => ["30\n"];
        yield 'a flag' => [true];
    }

    #[DataProvider('wrongThresholds')]
    public function testTheDeadStockRefusesOtherThresholds(bool|float|int|string $days): void
    {
        $this->expectException(InvalidReportJob::class);
        $this->expectExceptionMessageIsOrContains('the parameter "days" is a whole number from 1 to 3650');

        iterator_to_array($this->deadStock()->rows(new ReportRequest(Lines::period(), ['days' => $days])), false);
    }

    private function sales(): SalesReport
    {
        return new SalesReport(new SalesLedger(
            new InMemorySoldLines([
                Lines::sold('A', 1, 1000, 150, channel: 'store'),
                Lines::sold('A', 1, 1000, 0, channel: 'web'),
                Lines::sold('B', 1, 500, 0, 'USD', channel: 'web'),
            ]),
            new InMemoryReturnedLines([Lines::returned('A', 1, 800, channel: 'store')]),
        ));
    }

    private function sellThrough(): SellThroughReport
    {
        return new SellThroughReport(
            new InMemorySoldLines([Lines::sold('A', 1), Lines::sold('C', 1, category: '2026'), Lines::sold('A', 1), Lines::sold('D', 1, category: 'other', product: 'Other date')]),
            new InMemoryStockFigures([new StockFigure('A', 2, 1, 1, null), new StockFigure('C', 4, 0, 3, null), new StockFigure('D', 8, 0, 7, null)]),
            new InMemoryStockedSkus([new StockedSku('D', 'Date', 'fruit'), new StockedSku('B', 'Banana'), new StockedSku('A', 'Apple', 'fruit')]),
        );
    }

    private function deadStock(?int $days = null): DeadStockReport
    {
        $stock = new InMemoryStockFigures([
            new StockFigure('RECENT', 0, 0, 2, new DateTimeImmutable('2026-11-10T00:00:00Z')),
            new StockFigure('OLD', 0, 0, 3, new DateTimeImmutable('2026-06-01T00:00:00Z')),
            new StockFigure('EMPTY', 0, 0, 0, new DateTimeImmutable('2026-01-01T00:00:00Z')),
            new StockFigure('NEW', 0, 0, 1, null),
        ]);
        $skus = new InMemoryStockedSkus([new StockedSku('RECENT', 'Recent book'), new StockedSku('OLD', 'Old book', 'books'), new StockedSku('EMPTY', 'Empty shelf'), new StockedSku('NEW', 'New book')]);
        $clock = new MockClock('2026-11-20T12:00:00Z');

        return null === $days ? new DeadStockReport($stock, $skus, $clock) : new DeadStockReport($stock, $skus, $clock, $days);
    }
}
