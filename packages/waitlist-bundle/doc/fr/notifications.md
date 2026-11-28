<!-- phil-dev source-sha256=19f3723a84b1c2a82725a6456428452789f32db02af656b5e466e9b21ca662c8 -->
# Notifications

Quand phil/notifications-bundle est installé et activé, le bundle informe chaque participant de ce qui est arrivé à son
entrée : une place offerte, une offre expirée et, si on les nomme, une entrée inscrite ou une offre acceptée. Sans lui,
rien n'est envoyé, et les événements restent disponibles pour les écouteurs de l'application.

## Déclarer les types

Le bundle de notifications n'envoie que les types qu'il connaît. Déclarez-les, avec leurs canaux et leurs gabarits :

```yaml
# config/packages/phil_notifications.yaml
phil_notifications:
    types:
        waitlist.offered:
            channels: [site, email]
            importance: high
        waitlist.lapsed:
            channels: [site]
```

Le type de chaque événement vient des options `notifications` de `phil_waitlist` ; un type laissé à `null`, ou non
déclaré au bundle de notifications, n'envoie rien :

```yaml
# config/packages/phil_waitlist.yaml
phil_waitlist:
    notifications:
        offered: waitlist.offered
        lapsed: waitlist.lapsed
        joined: waitlist.joined    # declare it too
        accepted: ~
```

## Ce que porte une notification

| Champ | Valeur |
|---|---|
| Destinataire | Le participant de l'entrée |
| Sujet | `waitlist_entry` et l'identifiant de l'entrée |
| Données | `waitlist` : l'identifiant de la liste |
| Clé de doublon | Le type et l'identifiant de l'entrée : une notification par offre |

Ce qu'elle dit est écrit au moment de l'affichage, à partir des gabarits du type et du sujet que l'application résout.
Un `SubjectResolver` pour `waitlist_entry` lit l'entrée — et, par elle, la liste et son sujet — pour donner l'étiquette
et le lien, comme le titre de la séance et la page qui accepte l'offre :

```php
use Phil\NotificationsBundle\Application\Contract\SubjectResolver;
use Phil\NotificationsBundle\Application\ResolvedSubject;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;
use Phil\WaitlistBundle\Application\Waitlists;
use Phil\WaitlistBundle\Integration\Notifications\WaitlistNotifier;

final readonly class WaitlistEntrySubjects implements SubjectResolver
{
    public function __construct(private Waitlists $waitlists, private Sessions $sessions) {}

    public function resolve(SubjectReference $subject, ?string $locale): ?ResolvedSubject
    {
        if (WaitlistNotifier::SUBJECT !== $subject->type) {
            return null;
        }

        $entry = $this->waitlists->entry($subject->id);
        $session = $this->sessions->get($entry->waitlist()->subjectId());

        return new ResolvedSubject($session->title, '/waitlist/'.$entry->id);
    }
}
```

L'identifiant du participant doit être un identifiant que le `RecipientDirectory` du bundle de notifications connaît,
pour joindre le participant par courriel ou par texto ; le centre de notifications n'en exige aucun.
