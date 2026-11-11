<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Phil\ReportingBundle\Application\View\ChartDrawing;
use Phil\ReportingBundle\Application\View\MetricReading;
use Phil\ReportingBundle\Domain\Format\MetricFormat;
use Phil\ReportingBundle\Domain\ValueObject\Metric;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Domain\ValueObject\Series;
use Phil\ReportingBundle\Domain\ValueObject\SeriesSet;
use Phil\ReportingBundle\Tests\Unit\Fixture\VisitsSource;
use Phil\SvgChart\ChartType;
use Phil\SvgChart\Exception\InvalidChart;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ChartDrawing::class)]
#[CoversClass(MetricReading::class)]
final class ViewTest extends TestCase
{
    public function testAnSvgImage(): void
    {
        $set = new VisitsSource()->series(new Period(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-02')));
        $svg = new ChartDrawing()->svg($set, ChartType::Line, 'Visits');

        self::assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 320" role="img"', $svg);
        self::assertStringContainsString('<title>Visits</title>', $svg);
        self::assertStringContainsString('<path d="M136.33 36L333 130.4"', $svg);
        self::assertStringContainsString('Pages', $svg);
        self::assertStringContainsString('viewBox="0 0 300 200"', new ChartDrawing(300, 200)->svg($set, ChartType::Bar, 'Visits'));
        self::assertStringContainsString('<title>'.str_repeat('a', 200).'</title>', new ChartDrawing()->svg($set, ChartType::Bar, str_repeat('a', 250)));
        self::assertStringContainsString('<title>'.str_repeat('é', 200).'</title>', new ChartDrawing()->svg($set, ChartType::Bar, str_repeat('é', 250)));

        $this->expectException(InvalidChart::class);
        new ChartDrawing(50);
    }

    public function testBars(): void
    {
        $set = new VisitsSource()->series(new Period(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-02')));

        self::assertSame([
            ['label' => 'Mon', 'bars' => [
                ['series' => 'Pages', 'value' => 40.0, 'share' => 100, 'negative' => false],
                ['series' => 'Balance', 'value' => -10.0, 'share' => 25, 'negative' => true],
            ]],
            ['label' => 'Tue', 'bars' => [
                ['series' => 'Pages', 'value' => 20.0, 'share' => 50, 'negative' => false],
                ['series' => 'Balance', 'value' => 5.5, 'share' => 14, 'negative' => false],
            ]],
            ['label' => 'Wed', 'bars' => [
                ['series' => 'Pages', 'value' => null, 'share' => 0, 'negative' => false],
                ['series' => 'Balance', 'value' => 0.0, 'share' => 0, 'negative' => false],
            ]],
        ], new ChartDrawing()->bars($set));
        self::assertSame([['label' => 'a', 'bars' => [['series' => 's', 'value' => 0.0, 'share' => 0, 'negative' => false]]]], new ChartDrawing()->bars(new SeriesSet(['a'], [new Series('s', [0.0])])));
        self::assertSame(33, new ChartDrawing()->bars(new SeriesSet(['a', 'b'], [new Series('s', [1.0, 3.0])]))[0]['bars'][0]['share']);
        self::assertSame(67, new ChartDrawing()->bars(new SeriesSet(['a', 'b'], [new Series('s', [2.0, 3.0])]))[0]['bars'][0]['share']);
    }

    public function testAMetricInALocale(): void
    {
        $reading = new MetricReading();

        self::assertSame('1,234', $reading->format(new Metric(1234.4), 'en'));
        self::assertSame('1,234.40', $reading->format(new Metric(1234.4, decimals: 2), 'en'));
        self::assertSame('12.5%', $reading->format(new Metric(0.125, MetricFormat::Percent, decimals: 1), 'en'));
        self::assertSame('14%', $reading->format(new Metric(0.135, MetricFormat::Percent), 'en'));
        self::assertSame('CA$1,234.00', $reading->format(new Metric(1234.0, MetricFormat::Currency, 'CAD', 2), 'en'));
        self::assertSame('€1,234', $reading->format(new Metric(1234.0, MetricFormat::Currency, 'EUR'), 'en'));
        self::assertSame('1 234,5', $this->spaces($reading->format(new Metric(1234.5, decimals: 1), 'fr_CA')));

        self::assertSame('', $reading->number(null, 'en'));
        self::assertSame('1,234.57', $reading->number(1234.567, 'en'));
        self::assertSame('-3', $reading->number(-3.0, 'en'));
        self::assertSame('1 234,5', $this->spaces($reading->number(1234.5, 'fr')));
    }

    public function testTheChangeFromThePeriodBefore(): void
    {
        $reading = new MetricReading();

        self::assertSame(['direction' => 'up', 'percent' => '20%'], $reading->change(new Metric(12.0), new Metric(10.0), 'en'));
        self::assertSame(['direction' => 'down', 'percent' => '20%'], $reading->change(new Metric(8.0), new Metric(10.0), 'en'));
        self::assertSame(['direction' => 'flat', 'percent' => '0%'], $reading->change(new Metric(10.0), new Metric(10.0), 'en'));
        self::assertSame(['direction' => 'up', 'percent' => '50%'], $reading->change(new Metric(-5.0), new Metric(-10.0), 'en'));
        self::assertSame(['direction' => 'up', 'percent' => '12.3%'], $reading->change(new Metric(1123.4), new Metric(1000.0), 'en'));
        self::assertSame(['direction' => 'up', 'percent' => null], $reading->change(new Metric(3.0), new Metric(0.0), 'en'));
        self::assertSame(['direction' => 'down', 'percent' => null], $reading->change(new Metric(-3.0), new Metric(0.0), 'en'));
        self::assertSame(['direction' => 'flat', 'percent' => null], $reading->change(new Metric(0.0), new Metric(0.0), 'en'));
        self::assertSame('20 %', $this->spaces((string) $reading->change(new Metric(12.0), new Metric(10.0), 'fr')['percent']));
    }

    private function spaces(string $text): string
    {
        return str_replace(["\u{202f}", "\u{a0}"], ' ', $text);
    }
}
