# Reference

## Domain

| Type | Role |
|---|---|
| `Domain\Status\ComponentStatus` | `operational`, `maintenance`, `degraded`, `partial_outage`, `major_outage`; `worst()`, `isOutage()`. |
| `Domain\Status\IncidentState` | `investigating`, `identified`, `monitoring`, `resolved`. |
| `Domain\ValueObject\Component` | A component: id, name, description, group. |
| `Domain\ValueObject\Incident` | An incident: id, title, impact, components, updates newest first; `state()`, `startedAt()`, `resolvedAt()`, `isActiveAt()`. |
| `Domain\ValueObject\IncidentUpdate` | One update: moment, state, message. |
| `Domain\ValueObject\ScheduledMaintenance` | A maintenance: id, title, description, components, window; `isUpcomingAt()`, `isInProgressAt()`. |
| `Domain\ValueObject\ComponentState` | A component and its state on the page. |
| `Domain\ValueObject\StatusPage` | The page at one moment; `status()`, `groups()`, `componentName()`. |
| `Domain\Exception\StatusPageException` | The interface of the bundle's exceptions. |
| `Domain\Exception\InvalidStatusRecord` | A record that cannot be. |
| `Domain\Exception\UnknownSnapshotTarget` | A target the configuration does not declare. |

## Application

| Type | Role |
|---|---|
| `Application\Contract\ComponentStatuses` | The source of the states. |
| `Application\Contract\StatusRecords` | The store of incidents and maintenances. |
| `Application\Contract\SnapshotPublisher` | Where a snapshot goes. |
| `Application\Contract\PageRenderer` | The HTML of the page, served or standalone. |
| `Application\StatusPageBuilder` | Puts the page together at the clock's present (public service). |
| `Application\RecordsReader`, `Application\Records` | Read records from plain data. |
| `Application\Render\JsonDocument`, `Application\Render\AtomFeed`, `Application\Render\PageLinks` | The JSON, the feed, the addresses they carry. |
| `Application\SnapshotPush` | Writes the snapshot and gives it to each target (public service, with a target). |
| `Application\Snapshot\Snapshot`, `SnapshotFile`, `PushReport` | The files of a snapshot, what a push did. |
| `Application\Message\PushStatusSnapshot` | The message that asks a worker to push. |

## Infrastructure

| Type | Role |
|---|---|
| `Infrastructure\Source\DeclaredStatuses` | The states of the configuration. |
| `Infrastructure\Source\MonitoringStatuses` | The states from `phil/monitoring-bundle`, kept in a cache pool. |
| `Infrastructure\Records\ConfiguredRecords`, `JsonFileRecords` | Records of the configuration, of a JSON file. |
| `Infrastructure\Publisher\DirectoryPublisher`, `FlysystemPublisher` | The `directory` and `flysystem` targets. |
| `Infrastructure\Http\StatusPageController` | The page, the JSON and the feed. |
| `Infrastructure\Console\PushCommand` | `phil:status-page:push [target]`. |
| `Infrastructure\Messenger\PushStatusSnapshotHandler` | Handles `PushStatusSnapshot`. |
| `Infrastructure\Scheduler\StatusPageSchedule` | The schedule `phil_status_page`. |
| `Infrastructure\Twig\TwigPageRenderer` | The page drawn by Twig. |
| `Infrastructure\Twig\StatusPageExtension` | `phil_status_kit()`, `phil_status_variant()`. |
| `Infrastructure\Twig\KitComponents` | The names of the kit's recipes. |
| `Infrastructure\DependencyInjection\StatusPageServices`, `MonitoringPass`, `KitSubdirectoryPass` | The wiring. |

## Services

| Id | Class |
|---|---|
| `Phil\StatusPageBundle\Application\StatusPageBuilder` | `StatusPageBuilder` |
| `Phil\StatusPageBundle\Application\SnapshotPush` | `SnapshotPush`, with a target |
| `Phil\StatusPageBundle\Application\Contract\ComponentStatuses` | Alias of the source |
| `Phil\StatusPageBundle\Application\Contract\StatusRecords` | Alias of the store |
| `phil_status_page.publisher.<name>` | The publisher of each target |

## Templates and translations

`@PhilStatusPage/layout.html.twig`, `snapshot.html.twig`, `page.html.twig`, `_badge.html.twig`, `_moment.html.twig`,
`_incident.html.twig`, `_maintenance.html.twig`; the domain `phil_status_page` in English and French.
