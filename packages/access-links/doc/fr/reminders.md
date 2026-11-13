<!-- phil-dev source-sha256=c7ac0765cdf36e1bac6dbcabcda37cb1a2f546b20034f127f58d646fca63fd68 -->
# Rappels

## Le calendrier

```php
use Phil\AccessLinks\ReminderSchedule;

$schedule = new ReminderSchedule(['P3D', 'P7D']);   // three days, then seven days after the invitation
```

Les délais sont des durées ISO 8601 comptées depuis la création de l'invitation, en ordre croissant, dix au plus. Un
rappel qui tomberait à la clôture ou après n'est pas envoyé. Sans calendrier, `new ReminderSchedule()`, aucun rappel
n'échoit.

À la création d'une invitation, son premier rappel est fixé ; chaque rappel envoyé fixe le suivant ; une réponse ou une
révocation les arrête.

## Envoyer à la main

```php
foreach ($links->dueReminders(100) as $invitationId) {
    $links->remind($invitationId, $sender);
}
```

`remind()` réclame d'abord le rappel, par une seule écriture conditionnelle qui n'est valide que tant que l'invitation
n'a pas de réponse, n'est ni révoquée ni close, et que son rappel est toujours échu avec le compte lu : de deux
*workers*, un seul l'emporte. Elle émet ensuite un nouveau lien et remet l'`IssuedLink` à l'expéditeur. Un expéditeur qui
échoue après la réclamation perd ce rappel plutôt que de l'envoyer deux fois ; le suivant échoit selon le calendrier.

## Par Messenger

```php
use Phil\AccessLinks\Messenger\ReminderDispatcher;

$dispatcher->dispatchDue(100);   // one message per due reminder; returns how many
```

`ReminderDispatcher` envoie un `SendInvitationReminder` par invitation échue. Le message porte l'identifiant de
l'invitation et rien d'autre : ni destinataire, ni adresse, ni lien, que le *handler* émet de nouveau à son exécution.
Acheminez-le vers un transport asynchrone :

```yaml
framework:
    messenger:
        routing:
            Phil\AccessLinks\Messenger\SendInvitationReminder: async
```

`SendInvitationReminderHandler` appelle `remind()`. Un message traité deux fois, après que le destinataire a répondu, ou
pour une invitation supprimée depuis, n'envoie rien. Lancez le répartiteur depuis le Scheduler de Symfony ou une tâche
*cron*, toutes les heures par exemple.

## L'expéditeur

```php
use Phil\AccessLinks\Contract\ReminderSender;
use Phil\AccessLinks\IssuedLink;

final readonly class MailReminderSender implements ReminderSender
{
    public function sendReminder(IssuedLink $link): void
    {
        $member = $this->members->get($link->invitation->recipient);
        $this->mailer->send(new ReminderEmail($member->email, $link->url->toString()));
    }
}
```

L'expéditeur trouve comment joindre le destinataire à partir de son identifiant stable, et envoie le lien. Il ne met
jamais le lien dans un message d'une file d'attente : envoyez-le tout de suite, dans le *handler*.
