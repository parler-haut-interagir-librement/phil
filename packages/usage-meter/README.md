# phil/usage-meter

**How much each subject used of what it may use, and a refusal before an action takes it past its quota.**

phil/usage-meter counts what a subject uses — projects created, exports made, tokens a model spent, requests sent —
and holds it to a quota per period. A meter counts by calendar day, week, month or year in a time zone, by billing
periods of a number of months anchored on a date (each subject's own, when it has one), or over a rolling window cut
into buckets. `consume()` comes before an action and refuses it when it would go past the quota; `record()` counts
what was already used and never refuses. Both take an idempotency key, so that a retried request counts once, and
`refund()` gives back what a key counted when the action failed afterwards. A
subject's limit comes from the application — a plan, an override, the features of a subscription — or from the
meter's own default; the count starts again at each period's boundary.

It is for any PHP application that sells or gives tiers of use: a freemium site whose free tier allows three exports a
month, a service billed by the tokens or requests of each billing period, an API that allows so many calls an hour, a
tool that caps the projects of an account. With `phil/commerce-subscription-bundle`, a subscriber's limits are read
from the features of the offers they hold, written `usage:<meter>:<limit>`.

It is a plain PHP library whose only requirement is `psr/clock`. The counters live in a store:
in memory for tests and single runs, or in two tables of a Doctrine DBAL connection on PostgreSQL, MySQL, MariaDB or
SQLite, where an increment is one transaction around a single conditional `UPDATE`, so that two requests at the same
moment are both counted or one is refused, never lost. The tables join the schema the ORM generates, so a migration
creates them.

The design is small: a `Meter` names what is counted, its `PeriodPolicy` — `CalendarPeriod`, `BillingCycle`,
`RollingWindow` — and its default `Limit`; `Meters` holds them; a `UsageStore` keeps the `Counter`s; `LimitProvider`
and `AnchorProvider` are the application's ports; `UsageMeter` puts them together and answers with a `Usage`, or
throws `QuotaExceeded`.

## Status

| Part | State |
|---|---|
| Meters, limits (a number or none), calendar periods in a time zone, billing cycles with month-end days, rolling windows in buckets | Implemented and tested |
| `consume()` refusing before the quota is passed, `record()` counting past it, `usage()`, `allows()`, `limit()` | Implemented and tested |
| Idempotency keys: an increment counted once per subject, meter and key; a refused one leaves its key free | Implemented and tested |
| Limit providers in a chain; anchors per subject | Implemented and tested |
| Limits read from the features of `phil/commerce-subscription-bundle` | Implemented and tested |
| In-memory store; Doctrine DBAL store with an atomic conditional increment, schema and ORM listener, purge | Implemented and tested |
| `refund()` of what a key counted, for an action that then failed: bounded, idempotent, atomic in both stores, tested on MySQL 8.4 and PostgreSQL 17 | Implemented and tested |
| A Symfony bundle: meters from configuration, the DBAL store, rate limiters on controllers, console purge | Implemented in `phil/usage-meter-bundle` |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, declaring the services in Symfony. |
| [Meters and periods](doc/meters-and-periods.md) | `Meter`, `Meters`, calendar periods, billing cycles and anchors, rolling windows. |
| [Counting](doc/counting.md) | `consume()`, `record()`, `refund()`, `usage()`, `allows()`, idempotency keys, `Usage`. |
| [Limits](doc/limits.md) | `Limit`, limit providers, the chain, limits from subscription features. |
| [Storage](doc/storage.md) | The stores, the database tables, atomicity, the schema listener, the purge. |
| [Exceptions](doc/exceptions.md) | `UsageMeterException` and its three exceptions. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | The library's tests and mutation bar, and how to test code that meters. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```sh
composer require phil/usage-meter
```

```php
use Phil\UsageMeter\InMemoryUsageStore;
use Phil\UsageMeter\Limit;
use Phil\UsageMeter\Meter;
use Phil\UsageMeter\Meters;
use Phil\UsageMeter\Period\CalendarPeriod;
use Phil\UsageMeter\Period\RollingWindow;
use Phil\UsageMeter\UsageMeter;
use Symfony\Component\Clock\NativeClock;

$meter = new UsageMeter(
    new Meters(
        new Meter('exports', CalendarPeriod::month('America/Toronto'), Limit::of(3)),
        new Meter('requests', RollingWindow::hours(1), Limit::of(500)),
    ),
    new InMemoryUsageStore(),
    new NativeClock(),
);

$meter->consume($accountId, 'exports', 1, $requestId); // before the export; QuotaExceeded when it does not fit

$usage = $meter->usage($accountId, 'exports');
printf('%d of %s used, until %s', $usage->used, $usage->limit->toString(), $usage->resetsAt->format('Y-m-d'));
```

## Requirements

PHP 8.5 or later and `psr/clock`. `doctrine/dbal` for the database store, `doctrine/orm` for the schema listener,
`phil/commerce-subscription-bundle` for limits read from subscription features — each only when used.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
