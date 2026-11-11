# phil/monitoring-bundle

**The surveillance of a Symfony site: its health, a maintenance mode, the heartbeats of its scheduled tasks and its
Messenger queues.**

phil/monitoring-bundle gathers in one package what an operator watches on a running site. Health checks — a DBAL
connection, a cache pool on Valkey or Redis, a Messenger transport, a local directory, a Flysystem storage, or any
service of the application implementing `HealthCheck` — run behind the endpoints of external uptime monitors:
liveness, readiness, and a detailed view for the bearer of a token. A maintenance mode, enabled from the console,
answers `503` with `Retry-After` to everyone but the allowed addresses, roles and routes. Each scheduled task beats when
it runs, from Symfony Scheduler or from a cron job, and a dead-man switch alerts through Symfony Notifier the task that
did not run in its window. A page shows the Messenger transports, their failed messages, and retries or removes them,
for one role.

It is for any Symfony application that runs in production and is watched by someone. Another bundle — a public status
page, a dashboard — reads it through `MonitoringStatus`, a stable read model of the checks, the tasks and the
maintenance. Nothing the bundle shows carries more than names, statuses, counts and moments: no secret, no host, no
personal data.

It is written from scratch, on the components Symfony already ships: HttpKernel and HttpFoundation for the endpoints and
the maintenance mode, Messenger, Scheduler and Notifier for the queues and the tasks, Cache and Filesystem for the
stores, Console for the commands; DBAL, Flysystem, Twig and Security when the application has them.

## Status

| Part | State |
|---|---|
| Health checks: DBAL, cache pools, Messenger transports, directories, Flysystem, the application's own | Implemented and tested |
| Liveness, readiness and a detailed view behind a token, in JSON | Implemented and tested |
| Maintenance mode: flag file or cache key, `503` with `Retry-After`, allow-lists, console command | Implemented and tested |
| Heartbeats from Symfony Scheduler and from cron, a dead-man switch through Symfony Notifier | Implemented and tested |
| The page of the Messenger queues: counts, failed messages, retry and removal behind a role | Implemented and tested |
| `MonitoringStatus`, the read model for another bundle | Implemented and tested |
| Components of `phil/ux-bundle` in the pages | Not started: the pages extend the application's layout |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing, the routes, the stores. |
| [Configuration](doc/configuration.md) | Every key of `phil_monitoring`. |
| [Health checks](doc/health-checks.md) | The checks of the bundle, the endpoints of the monitors, the detailed view. |
| [Maintenance mode](doc/maintenance.md) | Enabling it, the allow-lists, the page visitors get. |
| [Heartbeats](doc/heartbeats.md) | Declaring tasks, beating from Scheduler or cron, the dead-man switch and its alerts. |
| [Messenger queues](doc/queues.md) | The page of the queues, its role, its actions. |
| [Extension points](doc/extension-points.md) | A check of your own, the stores, `MonitoringStatus`, the templates. |
| [Testing](doc/testing.md) | The bundle's tests, and how to test an application that uses it. |
| [Reference](doc/reference.md) | Every class, service, tag, route, command and template of the bundle. |

## Quick start

```sh
composer require phil/monitoring-bundle
```

```yaml
# config/packages/phil_monitoring.yaml
phil_monitoring:
    health:
        token: '%env(MONITORING_TOKEN)%'
        database: [default]
        cache_pools: [cache.app]
        messenger: { async: 100, failed: 0 }
    heartbeats:
        tasks:
            backup: { every: 86400, grace: 3600 }
```

```yaml
# config/routes/phil_monitoring.yaml
phil_monitoring_health:
    resource: '@PhilMonitoringBundle/config/routes/health.php'
    prefix: /_health
```

```sh
curl https://example.com/_health/ready
# {"status":"up"}
bin/console phil:monitoring:maintenance enable --retry-after=600
```

## Requirements

PHP 8.5 or later and Symfony 8.2 or later. `doctrine/dbal`, `league/flysystem`, `symfony/scheduler`, `symfony/routing`,
`twig/twig`, `symfony/security-core` and `symfony/security-csrf` — each only for the part that uses it.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
