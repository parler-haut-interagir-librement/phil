<!-- phil-dev source-sha256=1cfb09392867fb6272c7911542e44bb65f26c61e97585b903f1ae0b93ea42b92 -->
# Référence

## Classes

| Classe | Rôle |
|---|---|
| `Phil\MonitoringBundle\PhilMonitoringBundle` | Le bundle et sa configuration ; `HEALTH_CHECK_TAG` |
| `…\Domain\Status\HealthStatus` | `Up`, `Degraded`, `Down` ; `worst()`, `isAvailable()` |
| `…\Domain\Status\HeartbeatState` | `OnTime`, `Late`, `Never` |
| `…\Domain\ValueObject\Name` | La règle des noms des vérifications et des tâches ; `PATTERN`, `check()` |
| `…\Domain\ValueObject\CheckOutcome` | Un état et un résumé d'une ligne ; `up()`, `degraded()`, `down()`, `SUMMARY_LENGTH` |
| `…\Domain\ValueObject\CheckResult` | Une vérification telle qu'elle s'est exécutée : `name`, `outcome`, `durationMilliseconds`, `status()` |
| `…\Domain\ValueObject\HealthReport` | Une ronde de vérifications : `results`, `checkedAt`, `status()` |
| `…\Domain\ValueObject\ExpectedTask` | Une tâche déclarée : `name`, `periodSeconds`, `graceSeconds`, `dueBy()`, `stateAt()` |
| `…\Domain\ValueObject\HeartbeatStatus` | Une tâche dans son état actuel : `task`, `state`, `lastBeatAt`, `dueBy`, `lateSince()` |
| `…\Domain\ValueObject\MaintenanceState` | `since`, `retryAfterSeconds` |
| `…\Domain\Contract\HealthCheck` | Une vérification : `name()`, `check()` |
| `…\Domain\Contract\HeartbeatStore` | Le stockage des battements |
| `…\Domain\Contract\MaintenanceStore` | Le stockage du mode maintenance |
| `…\Domain\Exception\MonitoringException`, `InvalidValue` | Toute exception du bundle ; une valeur refusée |
| `…\Application\Contract\MonitoringStatus` | Le modèle de lecture pour un autre bundle |
| `…\Application\Contract\HeartbeatTask` | Un message planifié qui émet le battement d'une tâche |
| `…\Application\HealthChecker` | Exécute et chronomètre les vérifications : `report()` |
| `…\Application\Heartbeats` | `beat()`, `statuses()` |
| `…\Application\DeadManSwitch` | `watch()` : les tâches signalées par une ronde ; `LOCK_PREFIX`, le préfixe du nom du verrou d'une tâche |
| `…\Application\Maintenance` | `enable()`, `disable()`, `current()` |
| `…\Application\Monitoring` | L'implémentation de `MonitoringStatus` |
| `…\Application\Message\WatchHeartbeats` | Le message d'une ronde de l'interrupteur |
| `…\Application\Check\HeartbeatsCheck` | Les tâches sous forme de vérification ; `NAME` |
| `…\Application\Queue\Queues` | `overview()`, `failureTransports()`, `isFailureTransport()`, `failed()`, `retry()`, `remove()` |
| `…\Application\Queue\QueueSummary`, `FailedMessage` | Un transport, un message en échec, tels que la page les montre |
| `…\Infrastructure\Check\DatabaseCheck`, `CachePoolCheck`, `MessengerTransportCheck`, `FilesystemCheck`, `FlysystemCheck` | Les vérifications de la configuration |
| `…\Infrastructure\Store\CacheHeartbeatStore` | Les battements dans un pool PSR-6, sous `KEY_PREFIX` |
| `…\Infrastructure\Store\FileMaintenanceStore`, `CacheMaintenanceStore`, `MaintenanceRecord` | La maintenance sous forme de fichier indicateur ou de clé, et leur enregistrement commun |
| `…\Infrastructure\Http\HealthController` | `live()`, `ready()`, `details()` ; les noms des routes |
| `…\Infrastructure\Http\MaintenanceListener` | Le `503` du mode maintenance |
| `…\Infrastructure\Http\QueuesController` | `index()`, `retry()`, `remove()` ; les noms des routes et `CSRF_TOKEN_ID` |
| `…\Infrastructure\Console\HealthCommand`, `HeartbeatCommand`, `WatchCommand`, `MaintenanceCommand` | Les commandes |
| `…\Infrastructure\Scheduler\HeartbeatOnScheduledRun` | Le battement d'un message planifié |
| `…\Infrastructure\Messenger\WatchHeartbeatsHandler` | Le *handler* de `WatchHeartbeats` |
| `…\Infrastructure\DependencyInjection\MonitoringServices`, `MonitoringPass` | Les services de la configuration ; les transports des files d'attente |

## Services et étiquette

| Identifiant | Enregistré quand | Ce que c'est |
|---|---|---|
| `Phil\MonitoringBundle\Application\Contract\MonitoringStatus` | toujours | Le modèle de lecture, public |
| `…\Application\HealthChecker`, `Heartbeats`, `DeadManSwitch`, `Maintenance` | toujours | Les services à injecter |
| `…\Domain\Contract\HeartbeatStore` | toujours | Alias de `CacheHeartbeatStore`, ou de `heartbeats.store` |
| `…\Domain\Contract\MaintenanceStore` | toujours | Alias du stockage que choisit `maintenance.store` |
| `phil_monitoring.check.<name>` | une vérification est configurée | Une vérification de la configuration |
| `…\Application\Check\HeartbeatsCheck` | une tâche est déclarée | Les tâches sous forme de vérification |
| `…\Infrastructure\Http\MaintenanceListener` | toujours | Sur `kernel.request`, après le routeur et le pare-feu |
| `…\Infrastructure\Scheduler\HeartbeatOnScheduledRun` | Scheduler est installé | Sur `PostRunEvent` |
| `…\Infrastructure\Messenger\WatchHeartbeatsHandler` | toujours | Traite `WatchHeartbeats` |
| `…\Application\Queue\Queues` | `queues.enabled` et un bus Messenger | Les files d'attente |
| `…\Infrastructure\Http\QueuesController` | les files d'attente, Twig, le routeur, le vérificateur d'autorisation et le gestionnaire de jetons CSRF | La page des files d'attente |
| étiquette `phil_monitoring.health_check` | toujours | Les vérifications ; donnée par autoconfiguration à chaque `HealthCheck` |

## Routes

| Nom | Fichier | Méthode et chemin |
|---|---|---|
| `phil_monitoring_live` | `config/routes/health.php` | `GET /live` |
| `phil_monitoring_ready` | `config/routes/health.php` | `GET /ready` |
| `phil_monitoring_details` | `config/routes/health.php` | `GET /details` |
| `phil_monitoring_queues` | `config/routes/queues.php` | `GET /` |
| `phil_monitoring_queues_retry` | `config/routes/queues.php` | `POST /{transport}/{id}/retry` |
| `phil_monitoring_queues_remove` | `config/routes/queues.php` | `POST /{transport}/{id}/remove` |

## Commandes

| Commande | Ce qu'elle fait |
|---|---|
| `phil:monitoring:health` | Exécute les vérifications ; échoue tant que l'une est hors service |
| `phil:monitoring:heartbeat <task>` | Émet le battement d'une tâche ; échoue pour une tâche non déclarée |
| `phil:monitoring:watch` | Lance une ronde de l'interrupteur d'homme mort ; échoue tant qu'une tâche est en retard |
| `phil:monitoring:maintenance [enable\|disable\|status] [--retry-after=N]` | Active, désactive ou affiche le mode maintenance |

## Gabarits

| Gabarit | Ce que c'est |
|---|---|
| `@PhilMonitoring/maintenance.html.twig` | La page de maintenance, autonome ; variable `maintenance` |
| `@PhilMonitoring/queues.html.twig` | La page des files d'attente ; étend `layout` |
| `@PhilMonitoring/layout.html.twig` | Une mise en page simple avec les blocs `title` et `content` |
