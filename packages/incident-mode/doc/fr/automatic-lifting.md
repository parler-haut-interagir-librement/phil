<!-- phil-dev source-sha256=0b1924bb4a85a47166b4d87523fbd4104b9d6f60819654327c09fef7c559f0e4 -->
# Levée automatique

Un incident se termine à sa fin, que quelque chose s'exécute ou non : une activation échue n'est plus en vigueur, et
`isSuspended()` cesse de répondre oui. **La levée automatique inscrit cette fin** — `liftedAt` fixé à la fin,
`LiftReason::Expired`, aucun acteur — et envoie `IncidentLifted`, une seule fois.

## Le message différé

`IncidentMode` donne chaque activation déclenchée et prolongée à son `LiftScheduler`. `Messenger\MessengerLiftScheduler`
envoie `Messenger\LiftIncident`, qui porte l'identifiant de l'activation et rien d'autre, différé jusqu'à sa fin par un
`DelayStamp`. `Messenger\LiftIncidentHandler` appelle `liftIfDue()` : un incident prolongé depuis n'est pas encore échu,
et attend le message que sa prolongation a envoyé ; un incident déjà levé reste tel quel.

Acheminez le message vers un transport qui prend en charge les délais — Doctrine, Redis, AMQP ; pas `sync` :

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Phil\IncidentMode\Messenger\LiftIncident: async
```

## Le balayage récurrent

Un transport peut perdre un message. `Messenger\LiftExpiredIncidents` fait lever par `liftExpired()` chaque incident
échu, cent à la fois ; ajoutez-le à un calendrier de Symfony Scheduler :

```php
use Phil\IncidentMode\Messenger\LiftExpiredIncidents;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('incidents')]
final class IncidentSchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return new Schedule()->add(RecurringMessage::every('5 minutes', new LiftExpiredIncidents()));
    }
}
```

Sans Messenger, `NoLiftScheduler` est la valeur par défaut, et une tâche cron appelle `liftExpired()`.

## Dans un worker

`IncidentMode` et ses adaptateurs ne conservent rien entre deux appels ; le magasin en mémoire fait exception, vidé par
`reset()`, et est destiné aux tests.
