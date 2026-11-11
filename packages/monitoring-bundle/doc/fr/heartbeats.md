<!-- phil-dev source-sha256=669eeb55121b220d1f7b09d6ddcdccad04495153b40e7b60c9cd9d42021c6ee9 -->
# Battements de cœur

## Déclarer les tâches

```yaml
phil_monitoring:
    heartbeats:
        tasks:
            backup: { every: 86400, grace: 3600 }   # once a day, an hour of grace
            invoices: { every: 300 }                # every five minutes
```

Une tâche doit émettre un battement toutes les `every` secondes. Elle est en retard dès que son dernier battement date
de plus que `every` et `grace` additionnés. Une tâche qui n'a émis aucun battement depuis que les battements sont
conservés est `never`, et ne déclenche pas d'alerte, pour qu'un déploiement ne signale pas toutes les tâches d'un coup.
Seule une tâche déclarée émet un battement : le stockage garde une entrée par tâche de la configuration.

## Émettre un battement

Depuis Symfony Scheduler, donnez au message l'interface `HeartbeatTask` :

```php
use Phil\MonitoringBundle\Application\Contract\HeartbeatTask;

final readonly class BackUpDatabase implements HeartbeatTask
{
    public function heartbeatTask(): string
    {
        return 'backup';
    }
}
```

La tâche émet son battement une fois que le *handler* du message a terminé : une exécution qui lance une exception
n'en émet pas. Un message qui nomme une tâche non déclarée est journalisé comme avertissement.

Depuis une tâche cron, émettez le battement une fois le travail réussi :

```sh
0 3 * * * cd /srv/app && ./backup.sh && bin/console phil:monitoring:heartbeat backup
```

La commande échoue pour une tâche non déclarée. Depuis le code de l'application, `Heartbeats::beat()` indique si la
tâche est déclarée.

## L'interrupteur d'homme mort

Une ronde de l'interrupteur signale, une seule fois, chaque tâche en retard depuis sa dernière alerte : la tâche est
marquée et reste silencieuse jusqu'à son prochain battement. Lancez une ronde chaque minute, depuis un seul planning ou
une seule tâche cron :

```php
use Phil\MonitoringBundle\Application\Message\WatchHeartbeats;
use Symfony\Component\Scheduler\RecurringMessage;

$schedule->add(RecurringMessage::every('1 minute', new WatchHeartbeats()));
```

```sh
* * * * * cd /srv/app && bin/console phil:monitoring:watch
```

La commande affiche chaque tâche et échoue tant que l'une est en retard, pour un cron qui envoie ses échecs par
courriel.

## Une seule alerte pour plusieurs workers

Chaque ronde signale une tâche sous un verrou de Symfony Lock nommé `phil_monitoring.heartbeat.<task>`. Le verrou est
pris sans attente et tenu au plus `heartbeats.lock.ttl` secondes, 30 par défaut. Une ronde qui trouve le verrou pris
saute la tâche. La ronde qui le tient relit la marque d'alerte avant de notifier. Ainsi, deux *workers* ou deux
serveurs ne signalent jamais deux fois le même retard, même quand un second planning tourne par erreur.

```yaml
framework:
    lock: '%env(LOCK_DSN)%'        # a store every server shares: Redis, Valkey, a database…

phil_monitoring:
    heartbeats:
        lock:
            factory: lock.factory  # the application's LockFactory
            ttl: 30
```

La garantie vaut entre serveurs seulement si le stockage de la fabrique de verrous est partagé : le stockage `flock`
par défaut de FrameworkBundle couvre les *workers* d'un seul serveur. Un stockage de verrous en échec est journalisé au
niveau erreur, et la tâche attend la ronde suivante. Un verrou qui ne peut pas être libéré est journalisé comme
avertissement et expire de lui-même.

## Les alertes

Chaque alerte est une `Notification` de Symfony Notifier, sur les canaux et avec l'importance de `heartbeats.alerts` :

> Scheduled task "backup" is late
>
> It was expected to run again by 2026-11-09T04:00:00+00:00.

Elle va aux destinataires administrateurs du notificateur — `framework.notifier.admin_recipients` —, et à la
destination par défaut du canal pour un canal de clavardage. Elle nomme seulement la tâche et le moment où elle était
attendue. Une alerte qui ne peut pas être envoyée est journalisée au niveau erreur, et la tâche reste non marquée : la
ronde suivante réessaie. Sans Notifier, les tâches en retard sont marquées et rendues à la commande, qui les affiche.

Les tâches en retard rendent aussi la vérification `heartbeats` dégradée, pour qu'un moniteur qui lit la vue détaillée
les voie.
