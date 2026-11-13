<!-- phil-dev source-sha256=ca7128ca76baa5c7a5f7a224e1e7c7ddf7c5083834cb1e752cc88914ff30a1e6 -->
# Référence

## Domaine

| Type | Rôle |
|---|---|
| `Domain\Status\ComponentStatus` | `operational`, `maintenance`, `degraded`, `partial_outage`, `major_outage` ; `worst()`, `isOutage()`. |
| `Domain\Status\IncidentState` | `investigating`, `identified`, `monitoring`, `resolved`. |
| `Domain\ValueObject\Component` | Un composant : identifiant, nom, description, groupe. |
| `Domain\ValueObject\Incident` | Un incident : identifiant, titre, impact, composants, mises à jour la plus récente d'abord ; `state()`, `startedAt()`, `resolvedAt()`, `isActiveAt()`. |
| `Domain\ValueObject\IncidentUpdate` | Une mise à jour : moment, état, message. |
| `Domain\ValueObject\ScheduledMaintenance` | Une maintenance : identifiant, titre, description, composants, fenêtre ; `isUpcomingAt()`, `isInProgressAt()`. |
| `Domain\ValueObject\ComponentState` | Un composant et son état sur la page. |
| `Domain\ValueObject\StatusPage` | La page à un moment donné ; `status()`, `groups()`, `componentName()`. |
| `Domain\Exception\StatusPageException` | L'interface des exceptions du bundle. |
| `Domain\Exception\InvalidStatusRecord` | Un enregistrement qui ne peut pas être. |
| `Domain\Exception\UnknownSnapshotTarget` | Une cible que la configuration ne déclare pas. |

## Application

| Type | Rôle |
|---|---|
| `Application\Contract\ComponentStatuses` | La source des états. |
| `Application\Contract\StatusRecords` | Le magasin des incidents et des maintenances. |
| `Application\Contract\SnapshotPublisher` | Où va un instantané. |
| `Application\Contract\PageRenderer` | Le HTML de la page, servie ou autonome. |
| `Application\StatusPageBuilder` | Assemble la page au moment présent de l'horloge (service public). |
| `Application\RecordsReader`, `Application\Records` | Lisent des enregistrements à partir de données simples. |
| `Application\Render\JsonDocument`, `Application\Render\AtomFeed`, `Application\Render\PageLinks` | Le JSON, le flux, les adresses qu'ils portent. |
| `Application\SnapshotPush` | Écrit l'instantané et le donne à chaque cible (service public, avec une cible). |
| `Application\Snapshot\Snapshot`, `SnapshotFile`, `PushReport` | Les fichiers d'un instantané, ce qu'une publication a fait. |
| `Application\Message\PushStatusSnapshot` | Le message qui demande à un worker de publier. |

## Infrastructure

| Type | Rôle |
|---|---|
| `Infrastructure\Source\DeclaredStatuses` | Les états de la configuration. |
| `Infrastructure\Source\MonitoringStatuses` | Les états tirés de `phil/monitoring-bundle`, conservés dans un pool de cache. |
| `Infrastructure\Records\ConfiguredRecords`, `JsonFileRecords` | Les enregistrements de la configuration, d'un fichier JSON. |
| `Infrastructure\Publisher\DirectoryPublisher`, `FlysystemPublisher` | Les cibles `directory` et `flysystem`. |
| `Infrastructure\Http\StatusPageController` | La page, le JSON et le flux. |
| `Infrastructure\Console\PushCommand` | `phil:status-page:push [target]`. |
| `Infrastructure\Messenger\PushStatusSnapshotHandler` | Gère `PushStatusSnapshot`. |
| `Infrastructure\Scheduler\StatusPageSchedule` | L'horaire `phil_status_page`. |
| `Infrastructure\Twig\TwigPageRenderer` | La page dessinée par Twig. |
| `Infrastructure\Twig\StatusPageExtension` | `phil_status_kit()`, `phil_status_variant()`. |
| `Infrastructure\Twig\KitComponents` | Les noms des recettes du kit. |
| `Infrastructure\DependencyInjection\StatusPageServices`, `MonitoringPass`, `KitSubdirectoryPass` | Le câblage. |

## Services

| Identifiant | Classe |
|---|---|
| `Phil\StatusPageBundle\Application\StatusPageBuilder` | `StatusPageBuilder` |
| `Phil\StatusPageBundle\Application\SnapshotPush` | `SnapshotPush`, avec une cible |
| `Phil\StatusPageBundle\Application\Contract\ComponentStatuses` | Alias de la source |
| `Phil\StatusPageBundle\Application\Contract\StatusRecords` | Alias du magasin |
| `phil_status_page.publisher.<name>` | L'éditeur de chaque cible |

## Gabarits et traductions

`@PhilStatusPage/layout.html.twig`, `snapshot.html.twig`, `page.html.twig`, `_badge.html.twig`, `_moment.html.twig`,
`_incident.html.twig`, `_maintenance.html.twig` ; le domaine `phil_status_page` en anglais et en français.
