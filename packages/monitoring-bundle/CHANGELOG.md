# Changelog

All notable changes to `phil/monitoring-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `PhilMonitoringBundle` and the `phil_monitoring` configuration: the clock, the cache pool, the checks and the token
  of `health`, the store and allow-lists of `maintenance`, the tasks, store and alerts of `heartbeats`, and the role,
  limit and layout of `queues`.
- `HealthStatus`, `CheckOutcome`, `CheckResult` and `HealthReport`: the status of a check — up, degraded or down —, its
  summary on one line of 200 characters, its duration, and a round of checks as healthy as its worst.
- `HealthCheck`, tagged `phil_monitoring.health_check` by autoconfiguration, and `HealthChecker`, which runs the checks
  in order, times them on the clock, and counts a check that throws as down, naming only the class of its exception.
- The checks of the configuration: `DatabaseCheck` for DBAL connections, `CachePoolCheck` for PSR-6 pools, Valkey or
  Redis among them, `MessengerTransportCheck` with a threshold of messages, `FilesystemCheck` with a threshold of free
  space, `FlysystemCheck` for storages, through a probe file of its own deleted whatever happens, and
  `HeartbeatsCheck`, degraded while a task is late.
- `HealthController` and the routes of `config/routes/health.php`: liveness without a check, readiness answering `503`
  when a check is down, and the detailed view for the bearer of the token, `404` without one; JSON, never cached.
- The maintenance mode: `MaintenanceState`, `MaintenanceStore`, `FileMaintenanceStore` for a flag file — an empty file
  counting —, `CacheMaintenanceStore` for a key of a pool, `Maintenance`, and `MaintenanceListener`, which answers
  `503` with `Retry-After` in JSON, from the template `@PhilMonitoring/maintenance.html.twig` or in plain text, but to
  the allowed routes, addresses, networks and roles; the health endpoints are always served.
- The heartbeats: `ExpectedTask`, `HeartbeatState`, `HeartbeatStatus`, `HeartbeatStore`, `CacheHeartbeatStore`, and
  `Heartbeats`, which records the beats of declared tasks only; `HeartbeatStatus::lateSince()`, the moment a late task
  was due.
- `HeartbeatTask` and `HeartbeatOnScheduledRun`: a message of Symfony Scheduler beats its task once its handler
  returned.
- `DeadManSwitch`, `WatchHeartbeats` and `WatchHeartbeatsHandler`: a task late is alerted once through Symfony
  Notifier, to the admin recipients when the notifier has them, until it beats again; an alert that cannot be sent is
  logged and tried again. Each task is alerted under a lock of Symfony Lock, from the `LockFactory` of
  `heartbeats.lock`, held a bounded time and released once the round is done: two workers or two servers never alert
  the same lateness twice.
- `Queues`, `QueueSummary`, `FailedMessage`, `QueuesController`, the routes of `config/routes/queues.php` and the
  template `@PhilMonitoring/queues.html.twig`: the messages of each transport, the failed messages of each failure
  transport without their content nor the text of their errors, and their retry or removal, posted with a CSRF token,
  for one role.
- `MonitoringPass`: the transports of the queues read from the receivers of Messenger, the queues removed without a
  bus, and their page without Twig, the router, the authorization checker or the CSRF token manager.
- The commands `phil:monitoring:health`, `phil:monitoring:heartbeat`, `phil:monitoring:watch` and
  `phil:monitoring:maintenance`.
- `MonitoringStatus` and `Monitoring`: the read model of the checks, the tasks and the maintenance, for another bundle
  to show.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
