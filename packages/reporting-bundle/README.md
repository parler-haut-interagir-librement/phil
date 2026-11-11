# phil/reporting-bundle

**Dashboards and reports for Symfony applications: figures contributed by any bundle, charts drawn without a script,
reports exported, scheduled and delivered.**

phil/reporting-bundle gathers what an application measures in one place. Any bundle, or the application itself,
contributes a figure — orders taken, a renewal rate — as a `MetricSource`, and a series — visits by day, sales by
category — as a `SeriesSource`. The configuration places them on dashboards; Twig components show a dashboard, a
metric or a chart on any page, and an EasyAdmin back office shows a dashboard as one of its pages when EasyAdmin is
installed.

The charts are HTML, CSS and SVG, never a script. An SVG image is drawn on the server by `phil/svg-chart`, with a
title, a description that reads every value, and the table of its values beside it; bars of HTML and CSS are a table
themselves; a table alone is a third rendering. A metric shows its value written in the reader's locale, and its
change from the period before.

A report is a service with columns and rows, read row by row: exported to CSV, TSV or JSON, streamed as a download,
written from the command line, or scheduled with Symfony Scheduler and delivered by Mailer or Notifier from a
Messenger worker. A message carries an identifier only: the job, with its recipients and parameters, and the
exported file wait in stores readable by their owner — the claim check.

It is written from scratch. The library `phil/svg-chart` draws the images; Symfony UX, Messenger, Scheduler, Mailer,
Notifier, Console and EasyAdmin are each used when they are installed.

## Status

| Part | State |
|---|---|
| `MetricSource`, `SeriesSource`: the data-source port, found by key | Implemented and tested |
| `Period`, `Metric`, `Series`, `SeriesSet`, with limits; `PeriodPreset`, counted in the configured time zone | Implemented and tested |
| Dashboards in the configuration, restricted to a role when one is named | Implemented and tested |
| `PhilReporting:Dashboard`, `:LiveDashboard`, `:Metric`, `:Chart` components | Implemented and tested |
| SVG, HTML and table renderings; a stylesheet on the tokens of PHIL UX, no inline style | Implemented and tested |
| `ReportDefinition`, `ReportFormat` (CSV, TSV, JSON), formula cells neutralised, rows bounded | Implemented and tested |
| `ReportQueue`, `RunReport`, the job and export stores of the claim check | Implemented and tested |
| The `phil_reporting` schedule of Symfony Scheduler | Implemented and tested |
| The `mail` and `notification` deliveries | Implemented and tested |
| `ReportResponses` downloads, `phil:reporting:export`, the EasyAdmin page | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the stylesheet. |
| [Configuration](doc/configuration.md) | Every key of `phil_reporting`. |
| [Sources](doc/sources.md) | Contributing metrics and series, periods and limits. |
| [Dashboards](doc/dashboards.md) | The components, the renderings, their accessibility, EasyAdmin. |
| [Reports](doc/reports.md) | Definitions, formats, downloads, the command, schedules, deliveries and the claim check. |
| [Extension points](doc/extension-points.md) | What an application may replace or add, and when it is stable. |
| [Testing](doc/testing.md) | The bundle's tests, and testing code that uses it. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```php
use Phil\ReportingBundle\Application\Contract\MetricSource;
use Phil\ReportingBundle\Domain\ValueObject\Metric;
use Phil\ReportingBundle\Domain\ValueObject\Period;

final readonly class OrdersTaken implements MetricSource
{
    public function __construct(private OrderRepository $orders) {}

    public static function key(): string { return 'orders'; }

    public function title(): string { return 'Orders'; }

    public function metric(Period $period): Metric
    {
        return new Metric($this->orders->countBetween($period->start, $period->end));
    }
}
```

```yaml
# config/packages/phil_reporting.yaml
phil_reporting:
    timezone: America/Toronto
    dashboards:
        sales:
            title: Sales
            periods: [last_30_days, this_month, last_month]
            widgets:
                - { metric: orders, compare: true }
```

```twig
<twig:PhilReporting:LiveDashboard name="sales" />
```

## Requirements

PHP 8.5 or later, with `intl`, `json`, `mbstring` and `sodium`; Symfony 8.2.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
