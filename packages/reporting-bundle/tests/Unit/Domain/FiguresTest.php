<?php

declare(strict_types=1);

namespace Phil\ReportingBundle\Tests\Unit\Domain;

use Phil\ReportingBundle\Domain\Exception\InvalidFigures;
use Phil\ReportingBundle\Domain\Format\MetricFormat;
use Phil\ReportingBundle\Domain\ValueObject\Metric;
use Phil\ReportingBundle\Domain\ValueObject\Series;
use Phil\ReportingBundle\Domain\ValueObject\SeriesSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Metric::class)]
#[CoversClass(Series::class)]
#[CoversClass(SeriesSet::class)]
final class FiguresTest extends TestCase
{
    public function testAMetric(): void
    {
        $metric = new Metric(12.5);
        self::assertSame([12.5, MetricFormat::Number, '', 0], [$metric->value, $metric->format, $metric->currency, $metric->decimals]);

        $amount = new Metric(9.99, MetricFormat::Currency, 'CAD', 2);
        self::assertSame(['CAD', 2], [$amount->currency, $amount->decimals]);
        self::assertSame(6, new Metric(1.0, decimals: 6)->decimals);
        self::assertSame(0, new Metric(1.0, MetricFormat::Percent)->decimals);
    }

    public function testABadMetricIsRefused(): void
    {
        foreach ([
            'a metric is a finite number' => static fn (): Metric => new Metric(\INF),
            'an amount names its currency by its three capital letters' => static fn (): Metric => new Metric(1.0, MetricFormat::Currency, 'cad'),
            'a metric shows from 0 to 6 decimals' => static fn (): Metric => new Metric(1.0, decimals: 7),
            'a metric shows from 0 to 6 decimals ' => static fn (): Metric => new Metric(1.0, decimals: -1),
        ] as $problem => $build) {
            try {
                $build();
                self::fail($problem);
            } catch (InvalidFigures $invalidFigures) {
                self::assertSame('The figures are invalid: '.trim($problem).'.', $invalidFigures->getMessage());
            }
        }

        foreach (['CADX', "CAD\n"] as $currency) {
            try {
                new Metric(1.0, MetricFormat::Currency, $currency);
                self::fail($currency);
            } catch (InvalidFigures) {
            }
        }
    }

    public function testASeriesSet(): void
    {
        $set = new SeriesSet(['a', 'b'], [new Series('One', [1.0, null]), new Series('Two', [-3.5, 2.0])]);

        self::assertSame(['a', 'b'], $set->labels);
        self::assertSame(3.5, $set->largest());
        self::assertSame(0.0, new SeriesSet(['a'], [new Series('One', [null])])->largest());
        self::assertSame(0.0, new SeriesSet([], [])->largest());
        self::assertSame(2.0, new SeriesSet(['a'], [new Series('One', [2.0]), new Series('Two', [1.0])])->largest());
    }

    public function testTheLimitsOfASeriesSet(): void
    {
        self::assertCount(500, new SeriesSet(array_fill(0, 500, 'x'), [])->labels);
        self::assertCount(24, new SeriesSet([], array_fill(0, 24, new Series('s', [])))->series);
        self::assertCount(1, new SeriesSet([str_repeat('é', 200)], [new Series(str_repeat('é', 200), [1.0])])->labels);

        foreach ([
            'more than 500 labels' => static fn (): SeriesSet => new SeriesSet(array_fill(0, 501, 'x'), []),
            'more than 24 series' => static fn (): SeriesSet => new SeriesSet([], array_fill(0, 25, new Series('s', []))),
            'the series "s" has 1 values for 2 labels' => static fn (): SeriesSet => new SeriesSet(['a', 'b'], [new Series('s', [1.0])]),
            'a label is longer than 200 characters' => static fn (): SeriesSet => new SeriesSet([str_repeat('é', 201)], []),
            'a label is longer than 200 characters ' => static fn (): SeriesSet => new SeriesSet([], [new Series(str_repeat('é', 201), [])]),
            'the series "nan" holds a value that is not finite' => static fn (): Series => new Series('nan', [1.0, \NAN]),
        ] as $problem => $build) {
            try {
                $build();
                self::fail($problem);
            } catch (InvalidFigures $invalidFigures) {
                self::assertSame('The figures are invalid: '.trim($problem).'.', $invalidFigures->getMessage());
            }
        }
    }
}
