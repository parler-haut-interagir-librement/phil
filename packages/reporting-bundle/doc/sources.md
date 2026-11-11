# Sources

A source is how a bundle, or the application, puts a figure on a dashboard. The bundle knows no figure of its own.

## A metric

```php
use Phil\ReportingBundle\Application\Contract\MetricSource;
use Phil\ReportingBundle\Domain\Format\MetricFormat;
use Phil\ReportingBundle\Domain\ValueObject\Metric;
use Phil\ReportingBundle\Domain\ValueObject\Period;

final readonly class Revenue implements MetricSource
{
    public function __construct(private Payments $payments) {}

    public static function key(): string
    {
        return 'revenue';
    }

    public function title(): string
    {
        return 'Revenue';
    }

    public function metric(Period $period): Metric
    {
        return new Metric($this->payments->sumBetween($period->start, $period->end), MetricFormat::Currency, 'CAD', 2);
    }
}
```

A `Metric` is a finite number, its `MetricFormat` — `Number`, `Percent` for a share of one, `Currency` with its ISO
4217 code —, and 0 to 6 decimals. A widget with `compare` asks the source a second time, for the period before, and
shows the change as a direction and a percentage of the previous figure; none when the previous figure is zero.

## Series

```php
use Phil\ReportingBundle\Application\Contract\SeriesSource;
use Phil\ReportingBundle\Domain\ValueObject\Period;
use Phil\ReportingBundle\Domain\ValueObject\Series;
use Phil\ReportingBundle\Domain\ValueObject\SeriesSet;

final readonly class Visits implements SeriesSource
{
    public function __construct(private VisitLog $log) {}

    public static function key(): string
    {
        return 'visits';
    }

    public function title(): string
    {
        return 'Visits';
    }

    public function series(Period $period): SeriesSet
    {
        $days = $period->split('1 day');

        return new SeriesSet(
            array_map(static fn (Period $day): string => $day->start->format('M j'), $days),
            [new Series('Visits', array_map(fn (Period $day): ?float => $this->log->countBetween($day->start, $day->end), $days))],
        );
    }
}
```

A `SeriesSet` holds labels and series, each series with one value per label — `null` where it has none. It is
bounded to 500 labels, 24 series and labels of 200 characters, the limits of `phil/svg-chart`, so that a source costs
no more to draw than a large honest chart.

## Periods

A `Period` runs from its start included to its end excluded. `contains()` says whether a moment falls in it;
`split('1 day')`, `split('1 week')` or `split('1 month')` cuts it in consecutive buckets, the last cut at the end, at
most 500. A step that does not move forward is refused with `InvalidFigures`.

The presets are counted in whole days of the configured time zone, from the `clock` service:

| Preset | Period | The period before |
|---|---|---|
| `today`, `yesterday` | The day | The day before |
| `last_7_days`, `last_30_days` | The days ending today, today included | The same number of days before |
| `this_month`, `last_month` | The calendar month | The month before |
| `last_12_months` | Twelve calendar months ending with this one | The twelve before |
| `this_year`, `last_year` | The calendar year | The year before |

## Registration

With autoconfiguration, a service implementing `MetricSource` or `SeriesSource` is tagged
`phil_reporting.metric_source` or `phil_reporting.series_source`, and found by the key its `key()` returns. A tag that
names its own `key` wins, which registers one class under several keys:

```yaml
services:
    app.revenue_eur:
        class: App\Reporting\Revenue
        tags: [{ name: phil_reporting.metric_source, key: revenue_eur }]
```

A source is built only when a widget asks for it. It holds no state between requests: it reads what it measures each
time it is asked.
