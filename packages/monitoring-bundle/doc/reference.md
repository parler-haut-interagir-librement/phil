# Reference

## Classes

| Class | Role |
|---|---|
| `Phil\MonitoringBundle\PhilMonitoringBundle` | The bundle and its configuration; `HEALTH_CHECK_TAG` |
| `…\Domain\Status\HealthStatus` | `Up`, `Degraded`, `Down`; `worst()`, `isAvailable()` |
| `…\Domain\Status\HeartbeatState` | `OnTime`, `Late`, `Never` |
| `…\Domain\ValueObject\Name` | The rule of the names of checks and tasks; `PATTERN`, `check()` |
| `…\Domain\ValueObject\CheckOutcome` | A status and a summary of one line; `up()`, `degraded()`, `down()`, `SUMMARY_LENGTH` |
| `…\Domain\ValueObject\CheckResult` | A check as it ran: `name`, `outcome`, `durationMilliseconds`, `status()` |
| `…\Domain\ValueObject\HealthReport` | A round of checks: `results`, `checkedAt`, `status()` |
| `…\Domain\ValueObject\ExpectedTask` | A declared task: `name`, `periodSeconds`, `graceSeconds`, `dueBy()`, `stateAt()` |
| `…\Domain\ValueObject\HeartbeatStatus` | A task as it stands: `task`, `state`, `lastBeatAt`, `dueBy`, `lateSince()` |
| `…\Domain\ValueObject\MaintenanceState` | `since`, `retryAfterSeconds` |
| `…\Domain\Contract\HealthCheck` | A check: `name()`, `check()` |
| `…\Domain\Contract\HeartbeatStore` | The store of the beats |
| `…\Domain\Contract\MaintenanceStore` | The store of the maintenance mode |
| `…\Domain\Exception\MonitoringException`, `InvalidValue` | Every exception of the bundle; a value refused |
| `…\Application\Contract\MonitoringStatus` | The read model for another bundle |
| `…\Application\Contract\HeartbeatTask` | A scheduled message that beats a task |
| `…\Application\HealthChecker` | Runs and times the checks: `report()` |
| `…\Application\Heartbeats` | `beat()`, `statuses()` |
| `…\Application\DeadManSwitch` | `watch()`: the tasks alerted by a round; `LOCK_PREFIX`, the prefix of the name of the lock of a task |
| `…\Application\Maintenance` | `enable()`, `disable()`, `current()` |
| `…\Application\Monitoring` | The implementation of `MonitoringStatus` |
| `…\Application\Message\WatchHeartbeats` | The message of a round of the switch |
| `…\Application\Check\HeartbeatsCheck` | The tasks as a check; `NAME` |
| `…\Application\Queue\Queues` | `overview()`, `failureTransports()`, `isFailureTransport()`, `failed()`, `retry()`, `remove()` |
| `…\Application\Queue\QueueSummary`, `FailedMessage` | A transport, a failed message, as the page shows them |
| `…\Infrastructure\Check\DatabaseCheck`, `CachePoolCheck`, `MessengerTransportCheck`, `FilesystemCheck`, `FlysystemCheck` | The checks of the configuration |
| `…\Infrastructure\Store\CacheHeartbeatStore` | The beats in a PSR-6 pool, under `KEY_PREFIX` |
| `…\Infrastructure\Store\FileMaintenanceStore`, `CacheMaintenanceStore`, `MaintenanceRecord` | The maintenance as a flag file or a key, and their shared record |
| `…\Infrastructure\Http\HealthController` | `live()`, `ready()`, `details()`; the route names |
| `…\Infrastructure\Http\MaintenanceListener` | The `503` of the maintenance mode |
| `…\Infrastructure\Http\QueuesController` | `index()`, `retry()`, `remove()`; the route names and `CSRF_TOKEN_ID` |
| `…\Infrastructure\Console\HealthCommand`, `HeartbeatCommand`, `WatchCommand`, `MaintenanceCommand` | The commands |
| `…\Infrastructure\Scheduler\HeartbeatOnScheduledRun` | The beat of a scheduled message |
| `…\Infrastructure\Messenger\WatchHeartbeatsHandler` | The handler of `WatchHeartbeats` |
| `…\Infrastructure\DependencyInjection\MonitoringServices`, `MonitoringPass` | The services of the configuration; the transports of the queues |

## Services and tag

| Id | Registered when | What it is |
|---|---|---|
| `Phil\MonitoringBundle\Application\Contract\MonitoringStatus` | always | The read model, public |
| `…\Application\HealthChecker`, `Heartbeats`, `DeadManSwitch`, `Maintenance` | always | The services to inject |
| `…\Domain\Contract\HeartbeatStore` | always | Alias of `CacheHeartbeatStore`, or of `heartbeats.store` |
| `…\Domain\Contract\MaintenanceStore` | always | Alias of the store `maintenance.store` chooses |
| `phil_monitoring.check.<name>` | a check is configured | One check of the configuration |
| `…\Application\Check\HeartbeatsCheck` | a task is declared | The tasks as a check |
| `…\Infrastructure\Http\MaintenanceListener` | always | On `kernel.request`, after the router and the firewall |
| `…\Infrastructure\Scheduler\HeartbeatOnScheduledRun` | Scheduler is installed | On `PostRunEvent` |
| `…\Infrastructure\Messenger\WatchHeartbeatsHandler` | always | Handles `WatchHeartbeats` |
| `…\Application\Queue\Queues` | `queues.enabled` and a Messenger bus | The queues |
| `…\Infrastructure\Http\QueuesController` | the queues, Twig, the router, the authorization checker and the CSRF token manager | The page of the queues |
| tag `phil_monitoring.health_check` | always | The checks; given by autoconfiguration to every `HealthCheck` |

## Routes

| Name | File | Method and path |
|---|---|---|
| `phil_monitoring_live` | `config/routes/health.php` | `GET /live` |
| `phil_monitoring_ready` | `config/routes/health.php` | `GET /ready` |
| `phil_monitoring_details` | `config/routes/health.php` | `GET /details` |
| `phil_monitoring_queues` | `config/routes/queues.php` | `GET /` |
| `phil_monitoring_queues_retry` | `config/routes/queues.php` | `POST /{transport}/{id}/retry` |
| `phil_monitoring_queues_remove` | `config/routes/queues.php` | `POST /{transport}/{id}/remove` |

## Commands

| Command | What it does |
|---|---|
| `phil:monitoring:health` | Runs the checks; fails while one is down |
| `phil:monitoring:heartbeat <task>` | Beats a task; fails for a task not declared |
| `phil:monitoring:watch` | Runs a round of the dead-man switch; fails while a task is late |
| `phil:monitoring:maintenance [enable\|disable\|status] [--retry-after=N]` | Enables, disables or shows the maintenance mode |

## Templates

| Template | What it is |
|---|---|
| `@PhilMonitoring/maintenance.html.twig` | The maintenance page, standing alone; variable `maintenance` |
| `@PhilMonitoring/queues.html.twig` | The page of the queues; extends `layout` |
| `@PhilMonitoring/layout.html.twig` | A plain layout with the blocks `title` and `content` |
