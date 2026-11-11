# Testing

## The bundle's tests

`tests/Unit` checks the value objects and their refusals; the checker, its timing on a mock clock and its handling of a
check that throws; the heartbeats, the dead-man switch, its alerts once per lateness, to the admin recipients, its
retries when an alert fails, and its lock between two concurrent rounds; the maintenance service and the read model; the
queues over transports in memory; each check of the bundle against SQLite, array pools, a temporary directory and an
in-memory storage; the stores and the records of another shape they read; the endpoints, the maintenance listener and
its allow-lists, the page of the queues and its actions; the commands; the Scheduler listener and the watch handler; and
the configuration, the services it gives and the compiler pass.

`tests/Functional` boots a small application — FrameworkBundle with Messenger transports in memory, Twig, a frozen
clock — and goes through the endpoints, the maintenance mode from the command to the visitor, a late task from the
watch to readiness, and the watch message dispatched on the bus.

From the monorepo root:

```sh
vendor/bin/phpunit packages/monitoring-bundle/tests
```

The bundle is held to 100 % covered mutation score by Infection.

## Testing an application

In the test environment, keep the beats in an array pool, the maintenance flag in the test's directory, and the time in
hand:

```yaml
when@test:
    framework:
        cache:
            pools:
                cache.monitoring:
                    adapter: cache.adapter.array
    phil_monitoring:
        cache_pool: cache.monitoring
        maintenance:
            file: '%kernel.cache_dir%/maintenance.json'
    services:
        clock:
            class: Symfony\Component\Clock\MockClock
            arguments: ['2026-11-08 10:00:00 UTC']
            public: true
```

A test then beats a task with `Heartbeats`, moves the clock, and asserts what `MonitoringStatus` or `/_health/ready`
answers. A check of the application is a plain class: test it on its own, then once through `HealthChecker`.
