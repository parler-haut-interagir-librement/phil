# Extension points

## A check of your own

A service implementing `Phil\MonitoringBundle\Domain\Contract\HealthCheck` is tagged `phil_monitoring.health_check`
(`PhilMonitoringBundle::HEALTH_CHECK_TAG`) by autoconfiguration, and runs in every round after the bundle's:

```php
use Phil\MonitoringBundle\Domain\Contract\HealthCheck;
use Phil\MonitoringBundle\Domain\ValueObject\CheckOutcome;

final readonly class SearchIndexCheck implements HealthCheck
{
    public function __construct(
        private SearchClient $client,
    ) {
    }

    public function name(): string
    {
        return 'search';
    }

    public function check(): CheckOutcome
    {
        $lag = $this->client->indexingLag();

        return $lag > 300 ? CheckOutcome::degraded(sprintf('Indexing %d seconds behind.', $lag)) : CheckOutcome::up();
    }
}
```

A check answers `down` only when the site cannot work without what it checks. Its summary is shown to the bearer of
the monitoring token: never a secret, a host, a path or a person. A check keeps nothing between two rounds, and is fast,
since it runs on every readiness probe. A service registered without autoconfiguration carries the tag itself; the
checks run in the order they are registered.

## The heartbeat store

`heartbeats.store` names a service implementing `Phil\MonitoringBundle\Domain\Contract\HeartbeatStore`, which replaces
`CacheHeartbeatStore`: a database table, a key-value store. `lastBeat()` answers `null` for a task that never beat;
`beat()` records the moment and clears the alert; `isAlerted()` and `markAlerted()` keep whether the current lateness
was alerted. The store is shared by every worker and server.

## The maintenance store

`MaintenanceStore` is aliased to the store `maintenance.store` chooses. To keep the maintenance elsewhere, alias it to a
service of your own:

```yaml
services:
    Phil\MonitoringBundle\Domain\Contract\MaintenanceStore: '@App\Maintenance\DatabaseMaintenanceStore'
```

`current()` is called on every request: answer fast, and read afresh rather than keep the state in the service.

## The read model

`Phil\MonitoringBundle\Application\Contract\MonitoringStatus` is the stable interface another bundle reads — a public
status page, a dashboard, a widget of a back office:

| Method | Answer |
|---|---|
| `health()` | A `HealthReport`: its `results`, each a `CheckResult` with its `name`, `outcome` and `durationMilliseconds`, its `checkedAt`, and its overall `status()`. |
| `heartbeats()` | A list of `HeartbeatStatus`: the `task`, its `state`, its `lastBeatAt`, its `dueBy`, and `lateSince()` while it is late. |
| `maintenance()` | The `MaintenanceState` in force — `since`, `retryAfterSeconds` — or `null`. |

Inject the interface, never `Monitoring`. Its methods and the value objects they answer keep their shape within a major
version. `health()` runs every check: a page shown to the public keeps its answer for a while — in a cache pool, for a
minute — rather than running the checks on every view.

## The templates

The maintenance page is `maintenance.template`; the page of the queues extends `queues.layout`. To change the page of
the queues itself, override `@PhilMonitoring/queues.html.twig` in `templates/bundles/PhilMonitoringBundle/`. Its
variables are `layout`, `queues` (a list of `QueueSummary`), `failed` (the `FailedMessage` lists by failure transport)
and `csrf_token`.
