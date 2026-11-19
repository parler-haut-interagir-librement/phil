# phil/error-tracking-bundle

**The errors of a Symfony application, captured wherever they happen and kept in its own database.**

phil/error-tracking-bundle brings `phil/error-tracking` into a Symfony application. The exception a request ends in,
a message a worker failed to handle, the error a console command ends in and the records of a Monolog handler are all
captured through one guard that never throws, never captures while another capture runs — a failing database cannot
start a loop — and takes the same throwable once. Each event is scrubbed of personal data and secrets, grouped by its
fingerprint and kept through DBAL, with a cap of events per group and a purge by retention.

Administration pages, enabled explicitly and open to the users granted their role, list the groups by status and show
each one with its latest events and their traces, with the forms that resolve, ignore or reopen it; with EasyAdmin,
the same pages are grafted on the dashboards of the back office. An alert tells of
the first event of a group and of a regression: a notification to named recipients with phil/notifications-bundle, or
a chat message through the chatter of `symfony/notifier`, queued through Messenger by default. With
phil/monitoring-bundle, a health check says whether the store answers and whether new groups are flooding in. An
optional endpoint receives the uncaught errors of the browsers from a small Stimulus controller, checked by origin and
token, bounded in size and throttled per client.

It is for any Symfony application that wants its errors at home rather than at a third party: a site in its first
phase, a shop, a tool with its health, errors and backups on one page. It is built on the components
Symfony already ships: HttpKernel, Messenger, Console and Monolog for the capture, DBAL for the store, Twig, Security
and CSRF for the pages, Notifier for the alerts, RateLimiter for the throttles.

## Status

| Part | State |
|---|---|
| Capture of requests, failed messages, commands and Monolog records, without loops | Implemented and tested |
| Groups and capped events through DBAL, instants in UTC, the schema listener, the purge command | Implemented and tested |
| Administration pages by role: list, detail with traces, resolve, ignore, reopen | Implemented and tested |
| The same pages in an EasyAdmin back office, through `#[AdminRoute]`, and their menu link | Implemented and tested |
| Alerts for first events and regressions, by notification or chat, queued or sent at once | Implemented and tested |
| The degraded state of alerts without a destination: the log, the administration pages and `phil:error-tracking:status` | Implemented and tested |
| The health check of the store for phil/monitoring-bundle | Implemented and tested |
| The browsers' reports counted by a named limiter of phil/rate-limit-bundle | Implemented and tested |
| The endpoint of the browsers' errors and its Stimulus controller | Implemented and tested, the parser fuzzed, the controller's tests run with the suites |
| Source maps of minified scripts | Not started, planned after the first release: a browser's frame keeps the minified script's address and line |
| Reception of the Sentry envelope format | Not provided, by choice: the capture points are the interface, see [The Sentry envelope](doc/sentry-envelope.md) |
| A guide from sentry-symfony | Written: [Migrating from sentry-symfony](doc/migrating-from-sentry.md) |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the tables, the routes, the Monolog handler. |
| [Configuration](doc/configuration.md) | Every key of `phil_error_tracking`. |
| [Capture](doc/capture.md) | Each capture point, what it tags, arguments in messages, and the guard against loops. |
| [Storage](doc/storage.md) | The tables, the cap of events, the purge. |
| [Administration](doc/administration.md) | The pages, the role, the layout, the actions, an EasyAdmin back office. |
| [Alerts](doc/alerts.md) | The notifications and the chat messages, queued or sent at once, and the degraded state. |
| [Browser errors](doc/browser-errors.md) | The endpoint, its checks, and the Stimulus controller. |
| [The Sentry envelope](doc/sentry-envelope.md) | Why the endpoint does not read the Sentry format. |
| [Migrating from sentry-symfony](doc/migrating-from-sentry.md) | Moving an application from the Sentry SDK to the capture points, step by step. |
| [Extension points](doc/extension-points.md) | The store, the alerter and the ignored exceptions. |
| [Testing](doc/testing.md) | The bundle's tests, the fuzz target, and how to test an application that captures errors. |
| [Reference](doc/reference.md) | Every class, service, route, command and template of the bundle. |

## Quick start

```sh
composer require phil/error-tracking-bundle
```

```yaml
# config/packages/phil_error_tracking.yaml
phil_error_tracking:
    release: '%env(default::APP_RELEASE)%'
    alerts:
        transport: discord
    admin: true
```

```yaml
# config/routes/phil_error_tracking.yaml
phil_error_tracking:
    resource: '@PhilErrorTrackingBundle/config/routes.php'
    prefix: /admin/errors
```

```sh
bin/console doctrine:migrations:diff     # the two tables of the bundle
bin/console doctrine:migrations:migrate
```

The exceptions of the requests, the failed messages and the errors of the commands are captured from then on; the
groups are listed at `/admin/errors` for `ROLE_ADMIN`.

## Requirements

PHP 8.5 or later, `phil/error-tracking`, `phil/ip-anonymizer`, `phil/url`, `phil/rate-limit`, Doctrine DBAL 4 and
Symfony 8.2; see [Installation](doc/installation.md) for the optional packages.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
