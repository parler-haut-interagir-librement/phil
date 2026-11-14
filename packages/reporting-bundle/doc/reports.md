# Reports

## A definition

```php
use Phil\ReportingBundle\Application\Contract\ReportDefinition;
use Phil\ReportingBundle\Application\Report\ReportRequest;

final readonly class SalesReport implements ReportDefinition
{
    public function __construct(private Orders $orders) {}

    public static function key(): string
    {
        return 'sales';
    }

    public function title(): string
    {
        return 'Sales';
    }

    public function columns(ReportRequest $request): array
    {
        return ['Order', 'Date', 'Total', 'Paid'];
    }

    public function rows(ReportRequest $request): iterable
    {
        foreach ($this->orders->iterateBetween($request->period->start, $request->period->end, $request->parameter('status')) as $order) {
            yield [$order->number, $order->placedAt->format('Y-m-d'), $order->total, $order->isPaid()];
        }
    }
}
```

The rows are read as they are written: yield them from a query iterated row by row, and an export holds one row in
memory at a time. Each row has one cell per column — a string, an integer, a float, a boolean or `null` —, or the
export stops with `InvalidReportJob`. Beyond `max_rows`, it stops with `ReportTooLarge`.

A `ReportRequest` is the period and the parameters, at most 32 scalars under keys of 1 to 64 letters, digits, dots,
dashes or underscores, each text at most 200 characters.

## The formats

| Key | File | Written |
|---|---|---|
| `csv` | `text/csv`, `.csv` | RFC 4180: commas, double quotes, CRLF. |
| `tsv` | `text/tab-separated-values`, `.tsv` | Tabs, quoted as CSV is. |
| `json` | `application/json`, `.json` | An array of objects keyed by the headings, one row per line. |

In CSV and TSV, a text cell starting with `=`, `+`, `-`, `@`, a tab or a carriage return is prefixed with an
apostrophe, so that a value a user typed never runs as a formula when a spreadsheet opens the file. JSON keeps the
values as they are, booleans and numbers typed.

The file is named after the report and the first and last days of its period: `sales-20261001-20261031.csv`.

## A download

`ReportResponses` streams a report from a controller of the application, which first checks who may read it:

```php
#[Route('/reports/sales.csv')]
#[IsGranted('ROLE_MANAGER')]
public function sales(ReportResponses $responses, Periods $periods): StreamedResponse
{
    return $responses->download('sales', 'csv', new ReportRequest($periods->period(PeriodPreset::LastMonth)));
}
```

An unknown report or format fails with `NotFound` before anything is sent. The response is an attachment,
`Cache-Control: private, no-store`, `X-Content-Type-Options: nosniff`.

The report is written to the response as it is read, row by row: a long report starts at once and holds no more than
a row in memory. Behind FrankenPHP, Caddy cuts a write that stays blocked for a minute — a client that stops reading
—, and the file arrives cut short. A report too long to download while the reader waits goes through
[the claim check](#in-a-worker-the-claim-check) instead.

## The command

```sh
bin/console phil:reporting:export                       # lists the reports
bin/console phil:reporting:export sales --format=json --period=this_month
bin/console phil:reporting:export sales --output=var/sales.csv
```

Without `--output`, the file goes to the standard output. An existing file is never overwritten.

## In a worker: the claim check

`ReportQueue::queue(ReportJob)` keeps the job in the job store and dispatches `RunReport`, which carries the job's
identifier only: the request, the parameters and the recipients never travel in the queue.

```php
$queue->queue(new ReportJob('sales', 'csv', new ReportRequest($period, ['status' => 'paid']), 'mail', ['manager@example.com']));
```

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\ReportingBundle\Application\Message\RunReport: async
            Phil\ReportingBundle\Application\Message\RunScheduledReport: async
```

In the worker, `ReportRunner` reads the job, writes the report to a temporary stream, keeps the file in the export
store, delivers it when the job names a delivery, then forgets the delivered file — unless `keep_delivered` is on —
and the job, and dispatches `ReportExported` with the identifiers of the job and of the file. A job without delivery
leaves its file in the export store, for the application to hand out by its identifier.

| Failure | What happens |
|---|---|
| A failure of the bundle: the job gone, an unknown report, format or delivery, too many rows, a row out of shape | `UnrecoverableMessageHandlingException`: never retried; the job is forgotten with its recipients. |
| A delivery that fails: a mail server out of reach | Retried by Messenger; the file of the attempt is removed, the job stays. |
| An export that cannot be written: the file not created, or its stream not flushed and closed, as on a full disk | `ExportFailed`, retried by Messenger; the file cut short is removed, the job stays. |

The `directory` store keeps each job as a JSON file and each export as a file, named by 32 random hexadecimal digits,
in a directory readable by its owner only; an identifier of another shape never reaches the disk. Nothing is
unserialized: a file `ReportJob::toArray()` did not write is refused. The `memory` store serves the tests and the
`sync://` transport, and is emptied when the container is reset.

## On a schedule

Each configured schedule becomes a recurring `RunScheduledReport` in the `phil_reporting` schedule of Symfony
Scheduler, every `every` from `from`. Its handler builds the job over the schedule's period at that moment and queues
it.

```sh
bin/console messenger:consume scheduler_phil_reporting async
```

## The deliveries

| Key | Delivers |
|---|---|
| `mail` | An e-mail to each recipient, the file attached, sent by the mailer's transports from the worker: the attachment never enters a queue. The file is read once, whole. The chart of a `ChartedReport` is the image of the e-mail where PNG charts are drawn. |
| `notification` | A notification on the configured channels of Notifier, with the report's title, period, number of rows and file name; no channel attaches the file. |

A delivery of the application implements `ReportDelivery`; see [Extension points](extension-points.md).

### The chart of a report in its e-mail

A report that also implements `ChartedReport` names the chart that goes with it: a series source, drawn over the
report's period, as bars by default.

```php
final class SalesReport implements ReportDefinition, ChartedReport
{
    // key(), title(), columns(), rows()…

    public function chart(): ReportChart
    {
        return new ReportChart('sales_by_day', ChartType::Line);
    }
}
```

Where PNG charts are drawn — see [Configuration](configuration.md#the-png-charts) —, the `mail` delivery adds an HTML
part to the e-mail: the text, then the chart as a PNG image shown in place (`cid:chart.png`), whose `alt` is the
chart's title; the file stays attached. E-mail clients do not show SVG, so there is no SVG fallback: without the
extension or its fonts, or when the image cannot be drawn, the e-mail is the text and its file, as for any report. A
source that does not exist fails the delivery with `NotFound`, never retried.
