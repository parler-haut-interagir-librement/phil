# Changelog

All notable changes to `phil/reporting-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.1-alpha - 2026-11-25

### Added

- `LiveRequestsTest`: the component through the requests of LiveComponent 3.8.1, which serves it under its name only,
  renders nothing of it on a 422 raised before its action, and answers a redirection without `X-Live-Url`.

### Changed

- The development requirements take `twig/twig` 3.31.1 or later, which fixes the advisories of its sandbox published on
  2026-11-24.
- The development requirements take `easycorp/easyadmin-bundle` 5.10.4 or later, the release that fixes
  GHSA-f7rw-7wx5-3c54: a link to a route, like the menu links of this bundle, passed its `routeParams` to the controller
  of that route as arguments.

### Security

- Conflicts with `symfony/ux-live-component` before 3.8.1, which fixes the advisories of 2026-11-24: a batched request
  without an action that rendered a component past its `#[IsGranted]`, and a component of its own route still reached
  through `/_components`. The development requirements take it at 3.8.1 and `symfony/ux-twig-component` at 3.8.
- Conflicts with `easycorp/easyadmin-bundle` below 5.10.4, the release that fixes GHSA-f7rw-7wx5-3c54: an application
  cannot install this bundle next to a vulnerable EasyAdmin, which stays optional.

## 0.2.0-alpha - 2026-11-24

Two reports for a learning system, the training hours and costs by period and
organisational unit, and the template of Quebec's 1 % of payroll for workforce skills development. A minor version
and not a patch: three ports join those of 0.1, and an application that connects a `TrainingSource` sees two more
reports among its own. See [Training hours and costs](doc/training.md) and
[The 1 % of payroll template](doc/quebec-one-percent.md).

### Added

- `TrainingSource`, `UnitDirectory` and `PayrollSource`: the activities of a learning system in a currency, the path of
  an organisational unit from its root, the payroll of a period; `TrainingActivity` — its day, hours in hundredths,
  cost and eligible cost in minor units, unit — and `UnitNode`, each bounded.
- `TrainingLedger`, the activities totalled by period — the whole period, or its months, quarters or years — and by
  unit, with the units below it or alone, in the order of the tree, at most 50 000 totals; `TrainingTotals`,
  `TrainingStep`, `CurrencyAmounts`.
- The report `training_hours`: hours, costs and eligible costs by period and unit, with the parameters `step`,
  `include_below`, `unit` and `locale`.
- The report `training_quebec_1_percent`: hours, costs, eligible costs, payroll, the required investment of 1 %, the
  eligible costs as a share of the payroll, the shortfall and the threshold of two million dollars, by period, with
  the parameters `step`, `payroll` and `locale`.
- `TrainingHeadings`, the titles and headings in the `phil_reporting` domain, in English and French;
  `TrainingParameters`; `TrainingReportsPass`, which leaves the training reports out of an application without a
  `TrainingSource`.

### Changed

- `Period::split()` declares a list that is never empty.
- Requires the latest minor of each dependency (`symfony/service-contracts` 3.8), and of each development requirement
  (`symfony/ux-live-component` 3.8.1, `twig/twig` 3.31, `symfony/ux-twig-component` 3.8, `easycorp/easyadmin-bundle`
  5.10, …): `symfony/ux-live-component` 3.8.1 fixes CVE-2026-133774 and CVE-2026-133778.

## 0.1.3-alpha - 2026-11-18

The jobs and exports of the `directory` store are readable by their owner alone from
the moment they exist, and an open store directory is narrowed to its owner. See
[The directory of the claim check](doc/installation.md#the-directory-of-the-claim-check).

### Added

- `DirectoryJobStore`'s and `DirectoryExportStore`'s argument `tightenOpenDirectory`, and the option
  `tighten_open_directory`, true by default: false refuses a directory open to its group or to other users instead of
  narrowing it.

### Changed

- The `directory` store narrows an existing directory open to its group or to other users to `0700` before it keeps a
  file, and fails, naming the directory and its mode, when it cannot.
- A job and an export are written as temporary files of the directory created `0600`, then given their names in one
  step once they are whole; a failure leaves no file, nor a temporary one. A directory reached through a symbolic link
  is used where the link points.

### Security

- A job, which holds its recipients and parameters, was written with the default mode before it was narrowed to
  `0600`, and so was an export: in a directory open to them, other users of the machine could read them while they
  were written. Neither is readable by another user at any moment.

## 0.1.2-alpha - 2026-11-15

### Added

- PNG charts, where the `resvg` PHP extension is loaded and `phil_reporting.png.fonts` lists the fonts to draw them
  with: `ChartDrawing::png()` and `hasPng()`, through the `Rasterizer` of phil/svg-chart; `ChartResponses`, the chart
  of a series source as a PNG download; `ChartedReport` and `ReportChart`, the chart a report names, which the `mail`
  delivery shows as an image of its HTML part. The options `png.scale` and `png.max_pixels`. Without the extension or
  the fonts, `PngUnavailable` says the charts are SVG only, and an e-mail goes with its file alone.
- `ExportFailed`: a file of the directory store that cannot be created, or a stream that cannot be flushed and closed,
  fails the export — the file cut short removed, the job left for Messenger to retry.
- The documentation of the back office's assets in EasyAdmin, and of the minute after which Caddy cuts a streamed
  download that its client stops reading.

### Changed

- The stylesheet's path is written to the `asset_mapper` extension, and only when AssetMapperBundle is registered,
  instead of `framework.asset_mapper`.
- `ReportStream::close()` checks that the stream was flushed and closed.
- `phil/svg-chart` 0.1.1 is required, for its rasterizer.

## 0.1.1-alpha - 2026-11-14

### Changed

- The caption of the bars of `render="html"` is hidden for screen readers alone by the bundle's stylesheet, with the
  class `phil-reporting-bars__caption`, instead of a class of PHIL UX: the templates carry no class of a kit and draw
  the same beside Tailwind.

## 0.1.0-alpha - 2026-11-11

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
