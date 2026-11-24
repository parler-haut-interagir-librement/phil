<?php

declare(strict_types=1);

namespace Phil\CommerceReportsBundle\Tests\Functional;

use Override;
use Phil\CommerceReportsBundle\PhilCommerceReportsBundle;
use Phil\CommerceReportsBundle\Tests\Fixture\Lines;
use Phil\CommerceReportsBundle\Tests\Functional\App\TestKernel;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\ReportingBundle\Application\Report\Reports;
use Phil\ReportingBundle\Application\Report\ReportStream;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * The three reports found by their keys among the reports of a booted kernel, and exported as CSV.
 */
#[CoversClass(PhilCommerceReportsBundle::class)]
final class CommerceReportsBundleTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }
    }

    public function testTheReportsAreRegistered(): void
    {
        self::assertSame(['commerce_dead_stock', 'commerce_sales', 'commerce_sell_through'], array_values(array_filter($this->reports()->keys(), static fn (string $key): bool => str_starts_with($key, 'commerce_'))));
    }

    public function testTheSalesExportedAsCsv(): void
    {
        self::assertSame(
            "Category,Name,Currency,\"Units sold\",\"Units returned\",\"Gross sales\",Discounts,Returns,\"Net sales\"\r\n"
            ."books,books,CAD,2,1,30.00,5.00,12.50,12.50\r\n"
            ."games,games,USD,1,0,45.00,0.00,0.00,45.00\r\n",
            $this->export('commerce_sales', ['by' => 'category']),
        );
    }

    public function testTheSellThroughExportedAsCsv(): void
    {
        self::assertSame(
            "SKU,Product,\"Starting stock\",\"Units received\",\"Units sold\",\"Sell-through (ppm)\",\"Sell-through (%)\"\r\n"
            ."BOOK-1,\"First book\",6,2,2,250000,25\r\n"
            ."BOOK-2,\"Second book\",4,0,0,0,0\r\n"
            ."GAME-1,\"A game\",0,0,1,,\r\n",
            $this->export('commerce_sell_through'),
        );
    }

    public function testTheDeadStockAfterTheConfiguredDays(): void
    {
        self::assertSame(
            "SKU,Product,Category,\"Units available\",\"Last sale\",\"Days without a sale\"\r\n"
            ."BOOK-2,\"Second book\",books,4,2026-05-01,203\r\n",
            $this->export('commerce_dead_stock'),
        );
    }

    /**
     * @param array<string, bool|float|int|string> $parameters
     */
    private function export(string $report, array $parameters = []): string
    {
        $stream = ReportStream::temporary();

        try {
            $this->reports()->write($report, 'csv', new ReportRequest(Lines::period(), $parameters), $stream);
            $stream->rewind();

            return $stream->contents();
        } finally {
            $stream->close();
        }
    }

    private function reports(): Reports
    {
        self::bootKernel();
        $reports = self::getContainer()->get('test.reports');
        self::assertInstanceOf(Reports::class, $reports);

        return $reports;
    }
}
