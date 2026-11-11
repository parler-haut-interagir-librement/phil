# Changelog

All notable changes to `phil/svg-chart` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles, and versions follow
[Semantic Versioning](https://semver.org/).

## 0.1.0-alpha - 2026-11-11

### Added

- `Chart`, `ChartType` and `Dataset`, and `Chart::fromConfig()`, which reads the type, labels, series, colours and
  title of a Chart.js configuration, ignores the other options, and refuses more than 500 labels, 24 series or texts
  of 200 characters, with the path of the value in the message; a colour other than `#rrggbb` is refused however the
  chart is built.
- `SvgChartWriter`: grouped bars from the zero line, lines broken at missing values, pies and doughnuts from the first
  series, round ticks, thinned labels, a legend in rows, the Okabe-Ito palette or one given, a font and a size.
- A `<title>` on every mark, a `<desc>` of every value and `role="img"`; every text escaped.
- `InvalidChart`, under the `SvgChartException` interface.
- Tests of every refusal and golden images of each kind of chart.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
