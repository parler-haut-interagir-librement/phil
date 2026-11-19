# Migrating from sentry-symfony

This guide moves a Symfony application from `sentry/sentry-symfony` to this bundle. The two do not read each other's
configuration: the application replaces the SDK on the server with the bundle's [capture points](capture.md), and in
the browser with the [Stimulus controller](browser-errors.md). The events stay in the application's own database from
then on; the ones already sent to a Sentry server stay there.

## Before you start

- **Tracing and profiling are not covered.** The bundle keeps errors. Spans, transactions, profiles and cron
  monitors of the SDK have no counterpart here: keep a tool of their own for them, or drop them.
- **Breadcrumbs, the user and the request body are never kept.** An event carries the reduced request — method,
  address, route, a few headers, the client's address truncated by `phil/ip-anonymizer` — and the tags of its capture
  point. There is no `send_default_pii` to turn on.
- **Run both for a while if you need to compare.** The bundle captures from its own listeners; it does not need the SDK
  gone to work. Remove the SDK once the groups at `/admin/errors` look right.

## 1. Install the bundle

Follow [Installation](installation.md): require the bundle, create its two tables, import the routes of the
administration pages, and run `phil:error-tracking:purge` from the scheduler.

## 2. Carry the options over

| sentry-symfony | phil_error_tracking | Notes |
|---|---|---|
| `dsn` | none | The events are written through DBAL: `storage.connection` names the connection. |
| `options.environment` | `environment` | `%kernel.environment%` by default. |
| `options.release` | `release` | Regressions are told by it: set it on each deployment. |
| `options.sample_rate` | `sample_rate` | The share of the events kept, from 0.0 to 1.0. |
| `options.ignore_exceptions` | `ignored_exceptions` | The classes and their subclasses are never captured. |
| `options.in_app_exclude`, `in_app_include` | `project_dir` | The frames under it and outside its `vendor/` are the application's. |
| `options.before_send` | `scrub_fields`, `ignored_exceptions` | The scrubber runs before anything is written; see the scrubbing document of `phil/error-tracking`. |
| `options.send_default_pii` | none | Personal data is scrubbed whatever the configuration. |
| `options.max_breadcrumbs` | none | No breadcrumb is kept. |
| `options.traces_sample_rate`, `tracing` | none | Not covered: see above. |
| `register_error_listener` | `capture.http` | On by default; `capture.http.client_errors` adds the statuses below 500. |
| `messenger.enabled` | `capture.messenger` | On by default; `capture.messenger.retries` captures each failed attempt. |
| `messenger.capture_soft_fails` | `capture.messenger.retries` | A failure that will be retried is captured only when it is true. |
| The console integration | `capture.console` | On by default. The arguments of a command are never kept. |

[Configuration](configuration.md) lists every key.

## 3. Replace the Monolog handler

A `sentry` handler in `monolog.yaml` becomes a `service` handler on the bundle's handler, whose level is
`capture.monolog_level`:

```yaml
monolog:
    handlers:
        errors:
            type: service
            id: phil_error_tracking.monolog_handler
```

A record that carries an exception is grouped as that exception; the log record of an exception the request listener
already captured is not captured twice.

## 4. Replace the calls in the code

| sentry-symfony | This bundle |
|---|---|
| `\Sentry\captureException($exception)` or `HubInterface::captureException()` | `SafeCapture::throwable($exception, new CaptureContext())` |
| `\Sentry\captureMessage('Export failed')` | a log record at the handler's level, `$logger->error('Export failed')` |
| `\Sentry\configureScope()` with `setTag()` | `new CaptureContext(context: ['order' => $order->id])`, scalar values only |
| `\Sentry\configureScope()` with `setUser()` | none: the user is never kept |
| `\Sentry\withScope()` | a `CaptureContext` given to that one capture |

`SafeCapture` never throws and never loops: a capture that fails is logged once and returns `null`. See
[Capture](capture.md) for every capture point and what it tags.

## 5. Replace the browser SDK

Remove the browser SDK and its loader script, enable `browser` in the configuration, import the endpoint's routes and
put the Stimulus controller on the page's body, as [Browser errors](browser-errors.md) shows. The controller sends the
type, the message, the stack and the page without its query; the endpoint checks the origin, the optional token, the
size and the rate. Remove the Sentry host from the `connect-src` of the Content Security Policy: the reports go to
the application's own origin.

## 6. Tell someone

The alerts replace the Sentry server's notifications: name recipients or an audience of `phil/notifications-bundle`,
or a chatter transport of `symfony/notifier`, as [Alerts](alerts.md) explains. Without any of them the alerts are in
the degraded state: `phil:error-tracking:status`, the administration pages and the log say so, and nothing fails.

## 7. Remove the old packages

```sh
composer remove sentry/sentry-symfony
```

Then delete `config/packages/sentry.yaml`, the bundle's line in `config/bundles.php` if Flex left it, the `SENTRY_DSN`
variable of each environment, and the browser SDK's package or script. Run `phil:error-tracking:status` on each
environment: the capture points it lists are on, the store answers, and the alerts have a destination.
