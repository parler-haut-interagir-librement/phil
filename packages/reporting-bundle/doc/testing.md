# Testing

## The bundle's tests

The suite `tests/Unit` tests the domain — the bounds of a period, its buckets, the limits of a metric and of a set of
series —; the presets in a time zone and the periods before them; the sources, dashboards and schedules of the
configuration; the SVG images and the bars; metrics and their changes in several locales; requests and jobs written
and read back, and every shape refused; the reports bounded and checked row by row; the runner with its deliveries
and failures; the formats, formula cells included; the directory and memory stores against one contract, identifiers
out of shape included; the queue, the handlers and the schedule; the deliveries; the components and the access to a
restricted dashboard; the downloads, the command and the EasyAdmin link; the configuration, the services and the keys
of the tagged services.

The suite `tests/Functional` boots a kernel with TwigComponent, LiveComponent, Messenger on its synchronous transport
and Mailer on its null transport, renders a dashboard, a live dashboard, a metric and a chart, and runs a scheduled
report to its e-mail.

From the monorepo root:

```sh
vendor/bin/phpunit packages/reporting-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection.

## Testing code that uses reports

Configure the `memory` store in the test environment and freeze the clock:

```yaml
# config/packages/test/phil_reporting.yaml
phil_reporting:
    store: memory
```

```php
$queue->queue(new ReportJob('sales', 'csv', new ReportRequest($periods->period(PeriodPreset::LastMonth))));

self::assertStringStartsWith("Order,Date,Total,Paid\r\n", array_values($exports->contents())[0]);
```

`InMemoryJobStore::ids()` and `InMemoryExportStore::contents()` show what the stores hold. A source or a report is
tested on its own with a `Period` built by hand.
