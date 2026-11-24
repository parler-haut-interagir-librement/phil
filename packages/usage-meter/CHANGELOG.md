# Changelog

All notable changes to `phil/usage-meter` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.1-alpha.

Development only.

### Changed

- The development requirements ask for the latest minor of each dependency (`doctrine/orm` 3.8, `doctrine/dbal` 4.6).

## 0.1.0-alpha - 2026-11-20

### Added

- `Limit`: a whole number from zero or no limit, `allows()`, `remaining()`, `orHigher()`, and `parse()` of
  `unlimited` or decimal digits.
- `Meter`, a name, a period policy and a default limit; `Meters`, the meters of an application by name.
- `PeriodPolicy` and `Window`: the stretch of time a quota covers at a moment, and the counter that receives what is
  counted then.
- `CalendarPeriod` and `CalendarUnit`: days, ISO weeks, months and years from local midnight in a time zone.
- `BillingCycle`: periods of 1 to 120 months counted from an anchor, the subject's own when it has one, an anchor on
  the 29th to 31st falling on the last day of shorter months.
- `RollingWindow`: the last so many seconds in 1 to 1440 buckets aligned on the Unix epoch.
- `UsageStore`, `Counter` and `Increment`: atomic increments under a ceiling, idempotency keys per subject and meter,
  refunds of what a key counted, bounded by it and idempotent, totals over a range of counters, and the purge of
  counters that no window counts any more.
- `LimitProvider`, `LimitChain` and `AnchorProvider`, the application's ports for limits and anchors.
- `UsageMeter`: `consume()` before an action, refused by `QuotaExceeded` when it would pass the quota, `record()`
  after it, never refused, both idempotent by key; `refund()` of what a key counted when its action failed;
  `usage()`, `allows()`, `limit()` and `purge()`; `Usage`, with
  `remaining()`, `allows()`, `isExceeded()` and whether an answer was replayed.
- `InMemoryUsageStore`, the counters of one process.
- `Dbal\DbalUsageStore`: the counters and keys in two tables of a Doctrine DBAL connection on PostgreSQL, MySQL,
  MariaDB or SQLite, an increment in one transaction around a conditional `UPDATE`, a refund in one transaction
  around the key's row read `FOR UPDATE`, the tables added to a schema by
  `configureSchema()`; `Dbal\UsageSchemaListener` adds them to the schema the ORM generates.
- `Subscription\FeatureLimits`: limits written `usage:<meter>:<limit>` in features, the most generous winning, read
  from `phil/commerce-subscription-bundle` by `fromSubscriptions()`.
- `UsageMeterException`, implemented by `InvalidUsage`, `UnknownMeter` and `QuotaExceeded`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
