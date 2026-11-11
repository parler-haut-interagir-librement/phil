<!-- phil-dev source-sha256=07bdc79d43a73ad8d50ae515b84f0aaab93dad42ec779c2a47067f07030205dd -->
# Points d'extension

## Une vérification à vous

Un service qui implémente `Phil\MonitoringBundle\Domain\Contract\HealthCheck` reçoit l'étiquette
`phil_monitoring.health_check` (`PhilMonitoringBundle::HEALTH_CHECK_TAG`) par autoconfiguration. Il s'exécute à chaque
ronde, après les vérifications du bundle :

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

Une vérification ne répond `down` que si le site ne peut pas fonctionner sans ce qu'elle vérifie. Son résumé est montré
au porteur du jeton de surveillance : jamais un secret, un hôte, un chemin ou une personne. Une vérification ne garde
rien entre deux rondes, et elle est rapide, car elle s'exécute à chaque sonde de disponibilité. Un service enregistré
sans autoconfiguration porte l'étiquette lui-même ; les vérifications s'exécutent dans l'ordre de leur enregistrement.

## Le stockage des battements

`heartbeats.store` nomme un service qui implémente `Phil\MonitoringBundle\Domain\Contract\HeartbeatStore` et remplace
`CacheHeartbeatStore` : une table de base de données, un stockage clé-valeur. `lastBeat()` répond `null` pour une tâche
qui n'a jamais émis de battement ; `beat()` enregistre le moment et efface l'alerte ; `isAlerted()` et `markAlerted()`
retiennent si le retard en cours a été signalé. Le stockage est partagé par chaque *worker* et chaque serveur.

## Le stockage de la maintenance

`MaintenanceStore` est un alias du stockage que choisit `maintenance.store`. Pour garder la maintenance ailleurs,
faites-en un alias d'un service à vous :

```yaml
services:
    Phil\MonitoringBundle\Domain\Contract\MaintenanceStore: '@App\Maintenance\DatabaseMaintenanceStore'
```

`current()` est appelé à chaque requête : répondez vite, et relisez l'état plutôt que de le garder dans le service.

## Le modèle de lecture

`Phil\MonitoringBundle\Application\Contract\MonitoringStatus` est l'interface stable que lit un autre bundle — une page
d'état publique, un tableau de bord, un *widget* d'administration :

| Méthode | Réponse |
|---|---|
| `health()` | Un `HealthReport` : ses `results`, chacun un `CheckResult` avec son `name`, son `outcome` et sa `durationMilliseconds`, son `checkedAt`, et son `status()` global. |
| `heartbeats()` | Une liste de `HeartbeatStatus` : la `task`, son `state`, son `lastBeatAt`, son `dueBy`, et `lateSince()` tant qu'elle est en retard. |
| `maintenance()` | Le `MaintenanceState` en vigueur — `since`, `retryAfterSeconds` — ou `null`. |

Injectez l'interface, jamais `Monitoring`. Ses méthodes et les objets-valeurs qu'elles rendent gardent leur forme au
sein d'une version majeure. `health()` exécute chaque vérification : une page publique garde sa réponse un moment —
dans un pool de cache, pendant une minute — plutôt que d'exécuter les vérifications à chaque affichage.

## Les gabarits

La page de maintenance est `maintenance.template` ; la page des files d'attente étend `queues.layout`. Pour modifier la
page des files d'attente elle-même, remplacez `@PhilMonitoring/queues.html.twig` dans
`templates/bundles/PhilMonitoringBundle/`. Ses variables sont `layout`, `queues` (une liste de `QueueSummary`), `failed`
(les listes de `FailedMessage` par transport d'échec) et `csrf_token`.
