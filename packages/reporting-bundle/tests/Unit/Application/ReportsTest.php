<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Application;

use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Exception\InvalidReportJob;
use Phil\ReportingBundle\Application\Exception\NotFound;
use Phil\ReportingBundle\Application\Exception\ReportTooLarge;
use Phil\ReportingBundle\Application\Report\ReportRequest;
use Phil\ReportingBundle\Application\Report\Reports;
use Phil\ReportingBundle\Application\Report\ReportStream;
use Phil\ReportingBundle\Infrastructure\Format\CsvFormat;
use Phil\ReportingBundle\Tests\Unit\Fixture\Reporting;
use Phil\ReportingBundle\Tests\Unit\Fixture\SalesReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(Reports::class)]
#[CoversClass(ReportTooLarge::class)]
#[CoversClass(NotFound::class)]
final class ReportsTest extends TestCase
{
    public function testAReportWritten(): void
    {
        $reports = Reporting::reports();
        $stream = self::stream();

        self::assertSame(3, $reports->write('sales', 'csv', Reporting::request(), $stream));
        self::assertSame("Day,Amount,Paid,Note\r\n2026-10-01,1.5,false,'=SUM(A1)\r\n2026-10-01,3,true,\r\n2026-10-01,4.5,false,\r\n", $this->read($stream));
        self::assertSame(0, $reports->write('sales', 'json', Reporting::request(['rows' => 0]), $stream = self::stream()));
        self::assertSame("[]\n", $this->read($stream));
        self::assertInstanceOf(SalesReport::class, $reports->definition('sales'));
        self::assertInstanceOf(CsvFormat::class, $reports->format('csv'));
        self::assertSame('sales-20261001-20261031.csv', $reports->filename('sales', 'csv', Reporting::request()));
        self::assertSame('sales-20261001-20261031.json', $reports->filename('sales', 'json', Reporting::request()));
        self::assertSame(['sales'], $reports->keys());

        // A numeric key, which PHP turns into an integer: the keys are given back as strings.
        $many = new Reports(new ServiceLocator(['b' => static fn (): SalesReport => new SalesReport(), 'a' => static fn (): SalesReport => new SalesReport(), '1' => static fn (): SalesReport => new SalesReport()]), new ServiceLocator([])); // @phpstan-ignore argument.type
        self::assertSame(['1', 'a', 'b'], $many->keys());
        self::assertSame(100_000, new ReflectionProperty(Reports::class, 'maxRows')->getValue($many));
    }

    public function testTheRowsAreBounded(): void
    {
        self::assertSame(5, Reporting::reports(5)->write('sales', 'csv', Reporting::request(['rows' => 5]), self::stream()));

        $this->expectExceptionObject(ReportTooLarge::of('sales', 5));

        Reporting::reports(5)->write('sales', 'csv', Reporting::request(['rows' => 6]), self::stream());
    }

    public function testEachRowHasOneCellPerColumn(): void
    {
        $report = new class implements ReportDefinition {
            public static function key(): string
            {
                return 'odd';
            }

            public function title(): string
            {
                return 'Odd';
            }

            public function columns(ReportRequest $request): array
            {
                return ['a', 'b'];
            }

            public function rows(ReportRequest $request): iterable
            {
                yield ['1', '2'];
                yield ['3'];
            }
        };
        $reports = new Reports(new ServiceLocator(['odd' => static fn (): ReportDefinition => $report]), new ServiceLocator(['csv' => static fn (): CsvFormat => new CsvFormat()]));

        $this->expectExceptionObject(InvalidReportJob::because('the row 2 of the report "odd" has 1 cells for 2 columns'));

        $reports->write('odd', 'csv', Reporting::request(), self::stream());
    }

    public function testAnUnknownReportOrFormat(): void
    {
        foreach ([
            'There is no report "nope".' => static fn (): mixed => Reporting::reports()->write('nope', 'csv', Reporting::request(), self::stream()),
            'There is no report format "xlsx".' => static fn (): mixed => Reporting::reports()->write('sales', 'xlsx', Reporting::request(), self::stream()),
            'There is no report format "tsv".' => static fn (): mixed => Reporting::reports()->filename('sales', 'tsv', Reporting::request()),
        ] as $message => $call) {
            try {
                $call();
                self::fail($message);
            } catch (NotFound $notFound) {
                self::assertSame($message, $notFound->getMessage());
            }
        }
    }

    private static function stream(): ReportStream
    {
        return ReportStream::temporary();
    }

    private function read(ReportStream $stream): string
    {
        $stream->rewind();

        return $stream->contents();
    }
}
