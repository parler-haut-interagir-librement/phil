<!-- phil-dev source-sha256=f43b3366a063f13466c31a5669c771e35e930165c8d97e7ee430b89764c7689f -->
# Résumé et conservation

## Les résumés

Un destinataire qui a choisi un résumé quotidien ou hebdomadaire reçoit, au lieu d'un courriel pour chaque notification
d'un type qui le permet, un seul courriel qui contient ses notifications non lues que le dernier résumé ne contenait
pas, changées dans la journée ou la semaine, la plus ancienne en premier, jusqu'à `digest.limit`. Une notification dans
laquelle une autre est regroupée après son résumé revient dans le suivant. Un résumé ne contient que ce que garde le
centre : un type sans le canal `site` est envoyé par courriel sur-le-champ.

`Digests::sweep()` trouve les destinataires qui ont de telles notifications, une page à la fois, et envoie à chacun de
ceux qui ont choisi cette fréquence son résumé, par un message `SendDigest` chacun quand un bus les porte.
`MailerDigestSender` le rédige avec `@PhilNotifications/email/digest.html.twig`, le rend avec le moteur de rendu du corps
de Twig et l'envoie sur-le-champ par les transports de Symfony Mailer — jamais par `MailerInterface`, qui mettrait sur
le bus un `SendEmailMessage` contenant l'adresse et chaque ligne du résumé quand il est acheminé ; un refus du
transport remonte, pour que le *worker* retente le message `SendDigest`. Le contexte du gabarit est `frequency` et
`notifications`, chacune avec ses `id`, `type`, `title`, `body`, `url` et `count` ; son objet est la traduction de
`digest.subject.daily` ou de `digest.subject.weekly`. Une application qui envoie autrement implémente `DigestSender`.

## Suivi des ouvertures et des clics

Certains fournisseurs de courriel comptent les ouvertures d'un courriel avec une image invisible et suivent ses clics
par des liens redirigés. Chaque résumé porte le `TrackingHeader` (`X-Track`) de Symfony Mailer, que les transports des
fournisseurs associent à leurs propres réglages ; l'en-tête d'un message l'emporte sur la valeur par défaut de toute
l'application, `framework.mailer.tracking` (l'option `tracking` de MailerBundle, `mailer.tracking`). Le résumé
désactive les deux, quelle que soit cette valeur par défaut.

Une application qui a demandé le consentement de ses membres implémente `EmailTrackingConsent` et la nomme dans
`digest.tracking_consent` : chaque résumé suit alors les ouvertures et les clics que le destinataire a acceptés.

```php
final readonly class MemberTracking implements EmailTrackingConsent
{
    public function __construct(private Members $members) {}

    public function opens(string $recipient): bool
    {
        return $this->members->consents($recipient, 'email.opens');
    }

    public function clicks(string $recipient): bool
    {
        return $this->members->consents($recipient, 'email.clicks');
    }
}
```

Un transport qui ne connaît aucun réglage de ce genre — SMTP simple, entre autres — envoie l'en-tête tel quel, sans
effet.

## Le calendrier

Avec Symfony Scheduler, le calendrier `phil_notifications` répartit `SendDigests` chaque jour à `digest.at` et chaque
semaine le `digest.weekday` à cette heure, et `PurgeNotifications` chaque jour à `retention.purge_at`, dans
`schedule.timezone`. Un *worker* l'exécute :

```sh
bin/console messenger:consume scheduler_phil_notifications
```

## La conservation

`Retention::purge()` supprime les notifications lues et archivées modifiées pour la dernière fois il y a plus de
`retention.read_days` jours, et les non lues il y a plus de `retention.unread_days` jours, mille à la fois, jusqu'à cent
mille par exécution. `bin/console phil:notifications:purge` l'exécute sur-le-champ.
