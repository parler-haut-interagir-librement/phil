<!-- phil-dev source-sha256=923a83b14dab61ac2982f0191cd057af560e10726e6448bfb9f07ea38e158c87 -->
# Configuration

```yaml
# config/packages/phil_outbox.yaml
phil_outbox:
    store: dbal                 # "dbal", a table of a DBAL connection; "memory", one process, for tests
    connection: default         # the DBAL connection of the libraries that record
    table: phil_outbox
    clock: clock                # a clock of Symfony
    dispatcher: event_dispatcher
    generic_types: []           # types rebuilt as Phil\Outbox\RecordedEvent
    relay:
        batch_size: 100         # 1 to 1000
        lease: 60               # seconds, 1 to 3600: longer than the slowest listener
        max_attempts: 10        # 1 to 100
        first_delay: 60         # seconds, doubled after each failure
        longest_delay: 86400    # seconds, not under the first delay
        lock: true              # one run at a time, through the lock factory
    retention: 604800           # seconds a delivered record is kept
    scheduler:
        enabled: false
        frequency: 60           # seconds between two ticks, 1 to 86400
        max_batches: 10         # batches of a tick, 1 to 1000
    messenger:
        enabled: false
        bus: messenger.default_bus
```

| Option | Valeur par défaut | Effet |
|---|---|---|
| `store` | `dbal` | Où les enregistrements sont conservés. `memory` les garde dans un seul processus, remis à zéro entre les requêtes d'un *worker* : pour les tests seulement, puisque rien ne joint une transaction |
| `connection` | `default` | La connexion DoctrineBundle de la table, de 1 à 64 lettres, chiffres ou `_` |
| `table` | `phil_outbox` | Le nom de la table, de 1 à 48 lettres minuscules, chiffres ou `_`, ne commençant pas par un chiffre |
| `clock` | `clock` | L'identifiant du service de l'horloge de Symfony qui date les enregistrements et cadence le relais |
| `dispatcher` | `event_dispatcher` | L'identifiant du service du répartiteur PSR-14 par lequel le relais informe les écouteurs |
| `generic_types` | aucun | Les types d'événement reconstruits sous forme de `RecordedEvent`, sans codec propre |
| `relay.batch_size` | 100 | Le plus grand nombre d'enregistrements d'un lot |
| `relay.lease` | 60 | Les secondes pendant lesquelles un lot garde ses enregistrements |
| `relay.max_attempts` | 10 | Les tentatives échouées après lesquelles un enregistrement est mort |
| `relay.first_delay` | 60 | Les secondes avant qu'un enregistrement qui a échoué une fois soit essayé de nouveau |
| `relay.longest_delay` | 86 400 | Le délai le plus long avant qu'un enregistrement soit essayé de nouveau |
| `relay.lock` | true | Si une seule exécution à la fois détient le verrou `phil_outbox.relay` de `lock.factory`, quand l'application en a une |
| `retention` | 604 800 | Les secondes pendant lesquelles un enregistrement livré est conservé avant que `phil:outbox:purge` le retire |
| `scheduler.enabled` | false | Un horaire `phil_outbox` qui relaie l'outbox ; voyez [Workers](workers.md) |
| `messenger.enabled` | false | Relaie les événements vers un bus de Messenger au lieu du répartiteur ; voyez [Messenger](messenger.md) |

## Les codecs

Tout service qui implémente `Phil\Outbox\Contract\EventCodec` reçoit l'étiquette `phil_outbox.codec` par configuration
automatique ; un service défini à la main prend lui-même l'étiquette. Les types des codecs, avec les types génériques,
forment la liste d'autorisation de l'outbox : enregistrer un autre type lance `UnknownEventType`, et deux codecs d'un
même type arrêtent le conteneur.

```yaml
services:
    App\Outbox\SetEventsCodec:
        tags: [phil_outbox.codec]
```

## Les écouteurs

Le relais répartit chaque événement reconstruit par le répartiteur configuré : un écouteur de l'application écoute la
classe de l'événement comme n'importe quelle autre.

```php
#[AsEventListener]
final class GrantReward
{
    public function __invoke(RecordedEvent $event): void
    {
        // idempotent: $event->id is the same each time this record is delivered
    }
}
```
