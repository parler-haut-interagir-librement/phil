<!-- phil-dev source-sha256=1cf926f17eb995f94bef8bf66fdf4490c037c58f99b48a6ff990caa1dfec7460 -->
# Envoi

## Types

Un type se déclare sous `phil_notifications.types`, par une clé : lettres minuscules, chiffres, `_`, `.` et `-`. Ses
gabarits sont des clés de traduction du domaine `phil_notifications`, `<key>.title` et `<key>.body` par défaut, que
l'application traduit dans chaque locale :

```yaml
# translations/phil_notifications+intl-icu.fr.yaml
comment.replied.title: '{actor} a répondu à {subject}'
comment.replied.body: '{count, plural, one {Une réponse} other {# réponses}}'
```

Chaque gabarit reçoit `%actor%` (le nom de l'acteur tiré de l'annuaire), `%subject%` (le libellé du sujet tiré de son
résolveur), `%count%` (combien de notifications il représente après regroupement) et chaque entrée de données par sa
clé. Avec un domaine ICU, les mêmes valeurs sont nommées sans les signes de pourcentage.

## Le point d'entrée

```php
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

$notifications->send(
    'comment.replied',
    [$author->id],                              // a string or a list, up to 1 000
    new SubjectReference('comment', $reply->id),
    ['event' => $event->id],                    // identifiers only
    actor: $replier->id,
);

$notifications->sendToAudience('event.cancelled', 'event:'.$event->id.':attendees', new SubjectReference('event', $event->id));

$notifications->send('event.reminder', $member->id, new SubjectReference('event', $event->id), dedupeKey: 'event:'.$event->id.':day-before');
```

Une `dedupeKey`, un identifiant, rend deux notifications identiques dans la fenêtre des doublons quand leur sujet et leurs
données ne suffisent pas ; voir [Livraison](delivery.md#la-fenêtre-des-doublons).

Tout est vérifié à l'appel : un type non déclaré est une `UnknownNotificationType`, un identifiant de mauvaise forme une
`InvalidNotification`. Un identifiant contient des lettres, des chiffres, `_`, `.`, `:` et `-`, jusqu'à 190
caractères : jamais une espace ni un `@`, pour qu'aucun nom, aucune phrase ni aucune adresse ne voyage avec une
notification.

## Publics

Un public est un nom que l'application donne à un groupe de destinataires. Un service `AudienceProvider`, configuré
automatiquement, le transforme en identifiants à la livraison de la notification, pour que le message ne porte que le
nom :

```php
final readonly class Attendees implements AudienceProvider
{
    public function supports(string $audience): bool { return str_starts_with($audience, 'event:'); }

    public function recipients(string $audience): iterable { /* yields identifiers, a page at a time */ }
}
```

Un public qu'aucun fournisseur ne prend en charge fait échouer la livraison avec `UnknownAudience`.

## Messenger

Le message `SendNotification` contient le type, les destinataires ou le public, le type et l'identifiant du sujet, les
données, l'acteur, l'origine et la clé des doublons. Avec un bus, un message pour plusieurs destinataires, ou pour un public, est séparé en
un message par destinataire, pour qu'un échec soit retenté pour ce seul destinataire ; seul le premier message publie
sur les canaux de diffusion. L'acheminement appartient à l'application : envoyées à un transport asynchrone, les
notifications sont livrées par un *worker*.

```yaml
framework:
    messenger:
        routing:
            Phil\NotificationsBundle\Application\Message\SendNotification: async
```

Une livraison qui n'a joint aucun canal et pourrait réussir plus tard lance `DeliveryFailed`, et le *worker* retente le
message ; voir [Livraison](delivery.md#le-résultat).

N'acheminez que `SendNotification`. Les expéditeurs du bundle — les canaux courriel, SMS, push et chat du mode
`first_success` et le résumé — envoient par les transports de Symfony Mailer (`mailer.transports`) et ceux du texter et
du chatter de Notifier (`texter.transports`, `chatter.transports`), jamais par `MailerInterface`, le texter ou le
chatter : quoi que l'application achemine et quoi que dise `framework.notifier.message_bus`, ils ne mettent ni adresse,
ni numéro de téléphone, ni texte rendu dans une file Messenger, et le refus d'un fournisseur est connu quand ils
rendent la main.

Le mode `fan_out` confie les autres canaux à Symfony Notifier lui-même, dont les canaux suivent la configuration de
l'application : avec `framework.notifier.message_bus` réglé et `SendEmailMessage`, `SmsMessage` ou `ChatMessage`
acheminés vers un transport, l'adresse ou le numéro de téléphone du destinataire et tout le texte attendent dans la
file, et dans sa file des échecs, aussi longtemps que le message y reste. Laissez ces messages sans acheminement et
gardez `framework.notifier.message_bus` désactivé. Une application qui les achemine malgré tout pour ses propres
messages garde des renseignements personnels dans son transport Messenger et dans sa file des échecs : il lui faut une
règle de conservation pour la file des échecs — `messenger:failed:show` pour regarder, `messenger:failed:remove` pour
purger — et un mot dans sa politique de confidentialité.

Chaque service de la livraison ne garde aucun état entre les messages, comme l'exige un *worker*.
