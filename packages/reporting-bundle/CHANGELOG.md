# Changelog

All notable changes to `phil/reporting-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## NEXT - YYYY-MM-DD

### Added

- `PhilReportingBundle`, configured under `phil_reporting`: the time zone, the size of the charts, the most rows of a
  report, the store and its directory, the deliveries, the dashboards and the schedules; the stylesheet mapped for
  AssetMapper as `phil-reporting`.
- `Period`, a span from its start included to its end excluded, cut in buckets by `split()`; `Metric`, with its
  `MetricFormat` — number, percent, currency —; `Series` and `SeriesSet`, bounded to 500 labels and 24 series;
  `InvalidFigures`.
- `PeriodPreset` — today, yesterday, the last 7 or 30 days, this or last month, the last 12 months, this or last year —
  with the period before it; `Periods`, counted in whole days of the configured time zone.
- `MetricSource` and `SeriesSource`, the data-source port: every service implementing one is found by its static
  `key()`, through `Sources`.
- `Dashboards`, `Dashboard` and `Widget` from the configuration, a period from the address accepted only among those a
  dashboard offers, and a dashboard restricted to a role refused without the role or without an authorization checker.
- The components `PhilReporting:Dashboard`, `PhilReporting:LiveDashboard`, `PhilReporting:Metric` and
  `PhilReporting:Chart`; `ChartDrawing`, the SVG image of `phil/svg-chart` and horizontal bars of HTML and CSS;
  `MetricReading`, a metric and its change written in a locale; the translations in English and French.
- `ReportDefinition` and `ReportFormat`, found by key through `Reports`, which writes a report row by row and stops it
  at the configured number of rows with `ReportTooLarge`; `CsvFormat`, `TsvFormat` and `JsonFormat`, the cells a
  spreadsheet would read as formulas neutralised; `ReportStream`, the stream a report is written to or read from,
  which accepts only an open stream.
- `ReportRequest` and `ReportJob`, written as JSON and read back without unserializing; `JobStore` and `ExportStore`,
  the claim check, kept in a private directory or in memory; `ReportRunner`, which exports, delivers and forgets, and
  dispatches `ReportExported`.
- `ReportQueue` and the messages `RunReport` and `RunScheduledReport`, carrying identifiers only, with their handlers;
  the failures of the bundle never retried.
- `ReportSchedule` and `Schedules`, and the `phil_reporting` schedule of Symfony Scheduler.
- `ReportDelivery`, with `MailDelivery`, the file attached to an e-mail sent by the mailer's transports, and
  `NotificationDelivery`, the report announced on the channels of Notifier.
- `ReportResponses`, a report streamed as a download; the command `phil:reporting:export`; `ReportingAdmin`, a
  dashboard as a page of EasyAdmin.
