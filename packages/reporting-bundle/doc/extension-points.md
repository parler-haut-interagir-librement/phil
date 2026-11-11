# Extension points

Every port lives in `Phil\ReportingBundle\Application\Contract`. Until the first release, each may still change; it
is stable from 1.0.

## `MetricSource` and `SeriesSource`

The data-source port: how a bundle or the application contributes a figure or series to the dashboards. See
[Sources](sources.md).

```php
interface MetricSource
{
    public static function key(): string;

    public function title(): string;

    public function metric(Period $period): Metric;
}

interface SeriesSource
{
    public static function key(): string;

    public function title(): string;

    public function series(Period $period): SeriesSet;
}
```

## `ReportDefinition`

A report: its columns and its rows for a request. See [Reports](reports.md).

## `ReportFormat`

A file format beside CSV, TSV and JSON: its key, media type and extension, and how it writes the headings and the
rows to a `ReportStream` as they come. A format that needs every row before it writes — a spreadsheet — keeps the
bound of `max_rows` in mind.

```php
final readonly class MarkdownFormat implements ReportFormat
{
    public static function key(): string { return 'md'; }

    public function mediaType(): string { return 'text/markdown; charset=UTF-8'; }

    public function extension(): string { return 'md'; }

    public function write(ReportStream $stream, array $columns, iterable $rows): void
    {
        $stream->write('| '.implode(' | ', $columns)." |\n");

        foreach ($rows as $row) {
            $stream->write('| '.implode(' | ', array_map(strval(...), $row))." |\n");
        }
    }
}
```

## `ReportDelivery`

A way the file reaches its recipients beside `mail` and `notification`: a shared drive, a chat upload. It receives
the job, the `StoredExport` and the file as a `ReportStream` read from its start, and runs in the worker. An exception it
throws is retried by Messenger, the job kept.

## `JobStore` and `ExportStore`

The two halves of the claim check. Alias them to services of the application to keep the jobs and the files
elsewhere than the local directory — a database table, an object storage shared by several machines:

```yaml
services:
    Phil\ReportingBundle\Application\Contract\JobStore: '@app.reporting.job_store'
    Phil\ReportingBundle\Application\Contract\ExportStore: '@app.reporting.export_store'
```

`ReportJob::toArray()` and `ReportJob::fromArray()` give a job store the JSON to keep and read it back. A store throws
`JobUnavailable` for an identifier it holds nothing under, and ignores the removal of one. An export store reads the
file from a `ReportStream` and gives it back as another from `open()`, which the caller closes; `ReportStream::of()`
wraps what `fopen()` returns.

## Registration

With autoconfiguration, a service implementing one of `MetricSource`, `SeriesSource`, `ReportDefinition`,
`ReportFormat` or `ReportDelivery` is tagged `phil_reporting.metric_source`, `phil_reporting.series_source`,
`phil_reporting.report`, `phil_reporting.format` or `phil_reporting.delivery`, and found by its `key()`, or by the
`key` its tag names. A tagged service that does not implement the interface of its tag stops the container's
compilation.

## The event

`ReportExported` is dispatched through the PSR-14 dispatcher, when there is one, once a job is exported: the
identifiers of the job and of the file, and whether it was delivered.

## The templates

The templates of the components are `@PhilReporting/components/Dashboard.html.twig`, `Metric.html.twig`,
`Chart.html.twig` and `_table.html.twig`; override them under `templates/bundles/PhilReportingBundle/` as any bundle's.
The labels are in the `phil_reporting` translation domain.
