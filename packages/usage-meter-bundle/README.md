# phil/usage-meter-bundle

**Meters declared in configuration, counters kept in the database, and rate limiters that count on them.**

phil/usage-meter-bundle brings `phil/usage-meter` into a Symfony application. The meters are declared in the
`phil_usage_meter` configuration — a limit and a period each: calendar days, weeks, months or years in a time zone,
billing periods from an anchor, or a rolling window in buckets — and checked when the container is built. The counters
live in two tables of a Doctrine DBAL connection, which join the schema the ORM generates so that a migration creates
them; the memory of one process, or a store of the application's, serve as well. The limit providers of the
application are asked in a chain, the features of a subscriber's offers last when `phil/commerce-subscription-bundle`
is installed, and an anchor provider gives each subject its own billing day.

It is for any Symfony application that holds its users to quotas: a free tier of three exports a month, an API that
allows so many calls an hour per address, a plan that caps the tokens of a billing period. Its rate limiters are
limiters of `phil/rate-limit-bundle` that count on a meter: `#[RateLimit('api')]`, a route default or a form option
counts each request on the limiter's meter, for the request's key — the client's address or the signed-in user, kept as
keyed hashes, or the application's own —, so that a subscriber's limits and a calendar period apply to requests, and
`phil/rate-limit-bundle` answers `429 Too Many Requests` with `Retry-After` and its rate-limit headers when the quota is
reached. `bin/console phil:usage-meter:purge`, run once a day, forgets the counters no window counts any more.

It is built on the components Symfony already ships: Config and DependencyInjection for the
configuration, HttpKernel for the refusal of a malformed `Idempotency-Key`, Console for the purge.

## Status

| Part | State |
|---|---|
| Meters from configuration, each checked at build time: limit, calendar period, rolling window, billing cycle | Implemented and tested |
| Stores: DBAL with its tables in the ORM schema, in memory, or a service of the application | Implemented and tested |
| Limit providers in a chain, limits from subscription features, an anchor provider | Implemented and tested |
| Rate limiters of `phil/rate-limit-bundle` on meters: keys by address, by user or by the application's resolver, the subject's limits | Implemented and tested |
| Retries counted once per `Idempotency-Key` header, opt-in per limiter, bound to the request by an HMAC | Implemented and tested |
| `phil:usage-meter:purge` | Implemented and tested |
| Twig functions showing what a subject used and has left | Not started: templates read `UsageMeter::usage()` from the controller |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the tables and the migration. |
| [Configuration](doc/configuration.md) | Every key of `phil_usage_meter`: the store, the meters, the limits, the anchors. |
| [Rate limiting](doc/rate-limiting.md) | The limiters on meters, their keys, `Idempotency-Key`, the 429 answer and the headers. |
| [Console](doc/console.md) | `phil:usage-meter:purge` and its schedule. |
| [Extension points](doc/extension-points.md) | The application's store, limit providers and anchor provider. |
| [Exceptions](doc/exceptions.md) | What the bundle throws, and when. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test code that is metered or rate limited. |
| [Reference](doc/reference.md) | Every class, service and constant of the bundle. |

## Quick start

```sh
composer require phil/usage-meter-bundle
```

```yaml
# config/packages/phil_usage_meter.yaml
phil_usage_meter:
    meters:
        exports: { limit: 3, calendar: month, timezone: America/Toronto }
        requests: { limit: 600, rolling: 3600, buckets: 60 }
    rate_limiters:
        api: { meter: requests, key: ip }
```

```php
use Phil\UsageMeter\UsageMeter;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;

final class ExportController
{
    #[RateLimit('api')]
    public function __invoke(UsageMeter $meter, string $accountId): Response
    {
        $meter->consume($accountId, 'exports'); // QuotaExceeded when the month's three are spent
        // ...
    }
}
```

```sh
bin/console doctrine:migrations:diff   # the two counter tables
bin/console phil:usage-meter:purge     # once a day
```

## Requirements

PHP 8.5 or later, `phil/usage-meter`, `phil/rate-limit-bundle`, and the Symfony 8.2 components Config,
DependencyInjection, Console, HttpFoundation and HttpKernel. `doctrine/dbal` for the database store, `doctrine/orm` for its tables in the generated
schema, `phil/commerce-subscription-bundle` for limits from
subscription features — each only when used.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
