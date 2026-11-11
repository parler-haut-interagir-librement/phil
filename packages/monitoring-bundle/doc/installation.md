# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later | |
| `symfony/config`, `symfony/dependency-injection`, `symfony/http-kernel`, `symfony/http-foundation`, `symfony/console` | `^8.2` | The configuration, the services, the endpoints, the maintenance listener and the commands. |
| `symfony/messenger`, `symfony/notifier` | `^8.2` | The queues, the watch message and the alerts of late tasks. |
| `symfony/lock` | `^8.2` | The lock that keeps two rounds of the dead-man switch from alerting the same lateness. |
| `symfony/filesystem`, `psr/cache` | | The flag file and the cache stores. |
| `psr/clock`, `psr/log`, `psr/container` | | The time of the checks and the beats, the logged failures, the receivers of the queues. |
| `symfony/routing` | `^8.2` | The routes of the endpoints and of the page of the queues. |
| `doctrine/dbal` | `^4.5` | The checks of DBAL connections; not needed without `health.database`. |
| `league/flysystem` | `^3.30` | The checks of storages; not needed without `health.flysystem`. |
| `symfony/scheduler` | `^8.2` | The beats of scheduled messages; cron jobs beat through the command without it. |
| `twig/twig` | `^3.21` | The maintenance page and the page of the queues; the maintenance answer is plain text without it. |
| `symfony/security-core`, `symfony/security-csrf` | `^8.2` | The roles of the maintenance mode, and the page of the queues and its actions. |

## Installing

```sh
composer require phil/monitoring-bundle
```

Register the bundle, if Symfony Flex did not:

```php
// config/bundles.php
return [
    // ...
    Phil\MonitoringBundle\PhilMonitoringBundle::class => ['all' => true],
];
```

## The routes

The bundle declares no route of its own accord: import the endpoints of the monitors where they should answer, and the
page of the queues behind the firewall that knows the role it asks for.

```yaml
# config/routes/phil_monitoring.yaml
phil_monitoring_health:
    resource: '@PhilMonitoringBundle/config/routes/health.php'
    prefix: /_health

phil_monitoring_queues:
    resource: '@PhilMonitoringBundle/config/routes/queues.php'
    prefix: /admin/queues
```

The endpoints need no session and no user: put them outside any firewall that would redirect a monitor to a login
page, or in a stateless one.

## The stores

The heartbeats live in `cache.app` and the maintenance flag in `var/maintenance.json`. Both hold only when every
worker and server shares them:

- on one server, the defaults hold, provided `cache.app` is not an array or APCu pool;
- on several servers, give the bundle a pool on a shared adapter that does not evict, and keep the maintenance in it.

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            cache.monitoring:
                adapter: cache.adapter.redis
```

```yaml
# config/packages/phil_monitoring.yaml
phil_monitoring:
    cache_pool: cache.monitoring
    maintenance:
        store: cache
```

A pool that evicts loses beats — a task then reads as never run, which is not alerted — and ends a maintenance. On
Valkey or Redis, give that pool a server or database without an eviction policy.
