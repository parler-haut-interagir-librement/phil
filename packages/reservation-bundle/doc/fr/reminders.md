<!-- phil-dev source-sha256=817792fd5df81d7a218bb8d71c8cb01a981ec55d20f7f67d9424af0732392f8b -->
# Retenues et rappels

## Retenues expirées

Une retenue dont le moment est passé libère ses unités, qu'elle soit marquée ou non.
`phil:reservation:expire-holds` marque comme expirées les retenues dont le moment est venu, au plus `batch` par
exécution, pour que `ReservationExpired` parte pour chacune :

```sh
php bin/console phil:reservation:expire-holds
```

## Rappels

`phil:reservation:send-reminders` envoie les rappels dus, chacun une seule fois, au plus `batch` réservations par
exécution, les plus proches d'abord. Exécutez-la toutes les quelques minutes, par cron ou par une tâche du
Scheduler :

```sh
php bin/console phil:reservation:send-reminders
```

Pour chaque réservation confirmée qui commence dans l'horizon du rappel le plus éloigné des ressources, `Reminders`
demande au `ReminderSchedule` de la ressource quel rappel est dû, le note sur la réservation, puis l'envoie. Un rappel
dont le moment est passé sans envoi est dépassé par le suivant ; un expéditeur qui échoue perd ce rappel plutôt que de
l'envoyer deux fois.

Avec Messenger, chaque rappel est un message `SendReservationReminder` sur le bus par défaut, qui transporte
l'identifiant de la réservation et le décalage du rappel en minutes. L'application l'achemine vers un transport et le
traite — elle lit la réservation et son titulaire, et lui écrit par courriel, notification ou texto :

```php
#[AsMessageHandler]
final class RemindHolder
{
    public function __invoke(SendReservationReminder $reminder): void
    {
        // read the reservation $reminder->reservationId, then write to its holder
    }
}
```

Sans Messenger, une application qui enregistre son propre `ReminderSender` comme service de cette interface reçoit les
rappels par lui ; sans l'un ni l'autre, il n'y a ni service de rappel ni commande.
