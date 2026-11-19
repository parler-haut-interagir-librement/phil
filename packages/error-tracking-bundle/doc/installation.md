# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | |
| `phil/error-tracking` | `^0.1` | The events, fingerprints, scrubber, groups and tracker. |
| `phil/ip-anonymizer` | `^0.1` | The client's address is truncated before anything keeps it. |
| `phil/url` | `^0.1` | The addresses of the browsers' pages and the configured origins. |
| `phil/rate-limit` | `^0.1` | The guard that refuses a rate limit without its component, or on a pool local to each server in `prod`. |
| `doctrine/dbal` | `^4.5` | The groups and events are kept through DBAL. |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel`, `symfony/http-foundation` | `^8.2` | The configuration, the services and the capture of requests. |
| `symfony/notifier` | `^8.2` | The chat alerts. |
| `symfony/service-contracts`, `psr/log`, `psr/clock` | | The reset of the guard, the log of a failed capture, the clock. |
| `doctrine/orm` | `^3.7` | Optional: the tables added to the schema the ORM generates. |
| `symfony/messenger` | `^8.2` | Optional: the capture of failed messages and the queued alerts. |
| `symfony/console` | `^8.2` | Optional: the capture of commands, the purge and the status command. |
| `monolog/monolog` | `^3.0` | Optional: the log handler. |
| `symfony/rate-limiter` | `^8.2` | The throttles; the build fails without it unless each `rate_limit` is `false`. |
| `phil/monitoring-bundle` | `^0.1` | Optional: the health check of the store. |
| `phil/notifications-bundle` | `^0.1` | Optional: the alerts to recipients or an audience. |
| `phil/rate-limit-bundle` | `^0.1` | Optional: the browsers' reports counted by a named limiter. |
| `twig/twig`, `symfony/security-core`, `symfony/security-csrf`, `symfony/routing` | | Optional: the administration pages. |

## Installing

```sh
composer require phil/error-tracking-bundle
```

Register the bundle, if Symfony Flex did not:

```php
// config/bundles.php
return [
    // ...
    Phil\ErrorTrackingBundle\PhilErrorTrackingBundle::class => ['all' => true],
];
```

## The tables

With the ORM, the schema listener adds `phil_error_group` and `phil_error_event` to the schema of their connection:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, create them from `ErrorTables::configure()`: see [Storage](storage.md).

## The routes

The administration pages, when `admin` is enabled, are imported under a prefix of the application's choice, behind its
firewall:

```yaml
# config/routes/phil_error_tracking.yaml
phil_error_tracking:
    resource: '@PhilErrorTrackingBundle/config/routes.php'
    prefix: /admin/errors
```

The endpoint of the browsers, when `browser` is enabled:

```yaml
phil_error_tracking_browser:
    resource: '@PhilErrorTrackingBundle/config/routes_browser.php'
```

## The Monolog handler

The handler is a service, `phil_error_tracking.monolog_handler`; Monolog uses it once it is named as a handler:

```yaml
# config/packages/monolog.yaml
monolog:
    handlers:
        errors:
            type: service
            id: phil_error_tracking.monolog_handler
```

## Alerts and Messenger

Chat alerts are queued through Messenger by default: route
`Phil\ErrorTrackingBundle\Application\Message\AlertErrorGroup` to an asynchronous transport, or set `alerts.async` to
`false` to send them in the request.

## The release

Regressions are told by release. Give the bundle the version or commit that runs, from the environment for instance:

```yaml
phil_error_tracking:
    release: '%env(default::APP_RELEASE)%'
```

## Checking the installation

```sh
bin/console phil:error-tracking:status
```

The command lists the capture points that are on, says whether the store answers and how many groups it keeps, and
where the alerts go. A store that does not answer makes it fail; alerts without a destination are a warning, the
degraded state of [Alerts](alerts.md), and it still succeeds.
