<!-- phil-dev source-sha256=32cc5b12ae06bbff15bf19388cf83cfd2d16b0d4515b100cc0b6e6602e3e995c -->
# Livraison

Ce qui arrive à une notification pour un destinataire une fois son message traité : la fenêtre des doublons, la limite
de débit, le mode de son type — tous les canaux, ou l'un après l'autre jusqu'à ce qu'un l'accepte — et le résultat.

## Modes

Un type choisit comment ses canaux hors du centre sont servis :

| `delivery` | Ce qu'il fait |
|---|---|
| `fan_out` (par défaut) | Remet la notification à chaque canal du type que le destinataire n'a pas désactivé : Web Push par le `WebPushSender`, les autres canaux par Symfony Notifier, comme Notifier envoie lui-même une notification sur tous ses canaux |
| `first_success` | Essaie les canaux l'un après l'autre, dans l'ordre des `channels` du type, et s'arrête au premier dont le fournisseur accepte la notification |

```yaml
phil_notifications:
    types:
        account.password_changed:
            channels: [site, sms, web_push, email]
            delivery: first_success
            importance: urgent
```

Dans les deux modes, `site` garde la notification dans le centre et montre son message éphémère avant tout autre canal,
et les canaux de diffusion sont publiés une seule fois, hors du repli.

Un fournisseur qui accepte une notification l'a prise : **accepté veut dire accepté par le fournisseur**, non livré au
téléphone, au navigateur ou à la boîte de courriel. Passer au canal suivant quand un fournisseur signale plus tard le
message comme non livré — un webhook d'état de livraison, une seconde tentative après un délai — ne fait pas partie du
bundle.

## Le mode `first_success`

### Des tentatives sur-le-champ

Chaque canal est essayé sur-le-champ, dans la livraison du message `SendNotification` du destinataire, pour que la
réponse de son fournisseur soit connue avant que le canal suivant soit considéré :

| Canal | Envoyé par |
|---|---|
| `sms`, `sms/<transport>`, `push`, `push/<transport>` | Le `TexterInterface` de Symfony Notifier |
| `chat`, `chat/<transport>` | Le `ChatterInterface` de Symfony Notifier |
| `email`, `email/<transport>` | Les transports de Symfony Mailer (`mailer.transports`), le transport étant choisi par l'en-tête `X-Transport` : un courriel en texte brut, le titre comme objet, depuis `delivery.from` ou l'adresse des `headers` du mailer |
| `web_push` | Le `WebPushSender` |

**Gardez `framework.notifier.message_bus` désactivé** (`false`) pour ces canaux. Avec un bus, le texter et le chatter y
mettent le message au lieu de l'envoyer, son sort n'est connu que plus tard dans un *worker*, et le repli ne peut plus
passer au suivant : la tentative compte comme acceptée. N'acheminez jamais non plus `SendEmailMessage`, `SmsMessage` ou
`ChatMessage` vers un transport : le message en file contient l'adresse ou le numéro de téléphone du destinataire et
tout le texte, gardés par le transport — et par sa file des échecs — aussi longtemps que le message. Le canal courriel et
le résumé du bundle passent par les transports de Mailer, jamais par `MailerInterface`, pour qu'aucun
`SendEmailMessage` ne soit distribué. Acheminez plutôt `SendNotification`, qui ne porte que des identifiants :

```yaml
framework:
    notifier:
        message_bus: false
    messenger:
        routing:
            Phil\NotificationsBundle\Application\Message\SendNotification: async
```

Une application envoie sur un canal qui lui est propre — ou sur l'un de ceux ci-dessus, à sa façon — avec un
`ChannelSender`, autoconfiguré et interrogé avant les expéditeurs du bundle ; voir
[Points d'extension](extension-points.md#channelsender).

### Ce qui passe au canal suivant

Seulement un refus du fournisseur :

- une `TransportExceptionInterface` de Symfony Notifier ou de Symfony Mailer, qu'un expéditeur intercepte et signale
  comme `Refused` ;
- un refus des services de poussée, que le `WebPushSender` signale comme `Refused`.

Toute autre exception est une faute de l'application, non du fournisseur : elle remonte telle quelle, et Messenger s'en
charge. Un service de poussée qui répond `404` ou `410` ne connaît plus l'abonnement : l'abonnement est oublié, et le
canal compte comme un canal sans contact, non comme une panne.

### Les canaux sautés

Un canal est sauté, sans être appelé, avec sa raison :

| Raison | Quand |
|---|---|
| `opted_out` | Le destinataire l'a désactivé, pour le type ou pour tous les types |
| `quiet_hours` | Les heures de silence du destinataire retiennent les canaux qui dérangent, tous sauf `site` et `email` |
| `no_contact` | L'annuaire des destinataires ne donne rien de ce dont le canal a besoin, vérifié canal par canal : un `EmailRecipientInterface` avec une adresse pour le courriel, un `SmsRecipientInterface` avec un numéro de téléphone pour le SMS ; pour Web Push, aucun abonnement que les services de poussée connaissent encore |
| `circuit_open` | Le disjoncteur du canal est ouvert |
| `unavailable` | Aucun expéditeur ne sert le canal : pas d'expéditeur Web Push, pas de texter, de chatter ni de transport de Mailer, pas de `ChannelSender` de l'application |
| `refused` | Le fournisseur a refusé la notification : la tentative a eu lieu, et le canal suivant est essayé |

Un courriel laissé au résumé du destinataire compte comme accepté : le résumé prend la notification dans le centre, et les
canaux après `email` ne sont pas essayés.

### Les disjoncteurs

Avec phil/circuit-breaker-bundle, un canal est gardé par le circuit `notifications.<channel>.<transport>` —
`notifications.chat.slack` pour `chat/slack` — ou sinon `notifications.<channel>` — `notifications.sms` —, celui que
l'application déclare en premier. Un refus est enregistré comme un échec du circuit, une acceptation comme un succès ; un
destinataire injoignable ne dit rien du fournisseur et n'enregistre rien. Tant que le circuit est ouvert, son canal est
sauté ; dès qu'il laisse passer une sonde, la notification suivante est cette sonde.

```yaml
phil_circuit_breaker:
    cache_pool: cache.redis
    circuits:
        notifications.sms: { failure_rate_percent: 50, minimum_calls: 5, window_seconds: 60, open_seconds: 120 }
```

Un canal dont le circuit n'est pas déclaré n'a pas de disjoncteur. Les états des circuits doivent être partagés par
chaque serveur : le bundle de disjoncteurs refuse en production un pool local à chaque serveur, sauf si son option
`single_server` dit qu'il n'y en a qu'un.

## La fenêtre des doublons

La même notification est livrée à un destinataire une fois par fenêtre, cinq minutes par défaut, dans les deux modes.
Deux notifications sont les mêmes quand leur type et leur destinataire concordent et que, soit leur `dedupeKey`
concorde, soit, sans clé, leur sujet, leurs données, leur acteur et leur origine concordent. L'identifiant qu'une
livraison donne à une notification n'en fait jamais partie : il est nouveau à chaque tentative.

```php
$notifications->send('event.reminder', $member->id, new SubjectReference('event', $event->id), dedupeKey: 'event:'.$event->id.':day-before');
```

```yaml
phil_notifications:
    delivery:
        dedupe:
            enabled: true
            ttl: 300                   # the window of a type that names none
            store: ~                   # an IdempotencyStore service of phil/idempotency, such as a DbalIdempotencyStore
            cache_pool: cache.redis    # or a cache pool, with lock_factory, for a CacheIdempotencyStore
            lock_factory: lock.factory
    types:
        event.reminder:
            dedupe_ttl: 86400          # null for delivery.dedupe.ttl, 0 for no window
```

La livraison est réservée avec `IdempotencyGuard::begin()` de phil/idempotency, de façon atomique, avant la limite de
débit, pour que deux *workers* qui traitent le même message ne l'envoient jamais tous les deux. Une livraison qui se
termine `sent` garde la clé pour la fenêtre ; un refus de la limite de débit, un échec total ou une exception la libère,
pour que la tentative suivante s'exécute. Le centre garde la notification une fois par fenêtre sous une clé qui lui est
propre : une nouvelle tentative après un échec total ne l'ajoute pas deux fois au centre et ne montre pas de nouveau son
message éphémère.

Le stockage doit être partagé par chaque serveur, sinon chaque serveur enverrait une fois. Un `cache_pool` local à chaque
serveur — l'adaptateur de fichiers, APCu, tableau ou système de `symfony/cache`, ce que `cache.app` est par défaut — est
refusé en production, sauf si `delivery.single_server` vaut `true`. Le stockage de verrous de `lock_factory` doit lui
aussi être partagé.

## La limite de débit

Chaque destinataire reçoit un nombre limité de notifications hors du centre, compté par un limiteur de
`symfony/rate-limiter` que l'application nomme — une fenêtre glissante, comme cinq par heure —, un jeton par livraison
qui a un canal hors du centre à essayer. Un type `urgent` passe quel que soit le compte. Un destinataire au-delà de la
limite reçoit tout de même la notification dans le centre, avec son message éphémère ; rien d'autre n'est envoyé, et la
livraison se termine `rate_limited`.

```yaml
framework:
    rate_limiter:
        notification_per_user:
            policy: sliding_window
            limit: 5
            interval: '1 hour'
            cache_pool: cache.redis

phil_notifications:
    delivery:
        rate_limiter: notification_per_user
```

Le stockage du limiteur doit être partagé par chaque serveur : un stockage local est refusé en production, sauf si
`delivery.single_server` vaut `true`. Cette limite compte les notifications par destinataire ; ce n'est pas la limite des
requêtes HTTP de phil/rate-limit-bundle.

## Le résultat

`NotificationDispatcher::deliver()` retourne un `DeliveryResult` :

| Propriété | Ce qu'elle contient |
|---|---|
| `status` | `sent`, `duplicate`, `rate_limited` ou `failed` |
| `mode` | Le mode du type |
| `channels` | Les canaux qui ont pris la notification, dans l'ordre : `site`, un `email` laissé au résumé, le canal dont le fournisseur l'a acceptée |
| `channel()` | Le premier d'entre eux hors du centre — celui qu'a choisi `first_success` —, ou null |
| `skipped` | La raison de chaque canal sauté, par canal |
| `type`, `recipient` | Le type et l'identifiant du destinataire |

| Statut | Quand |
|---|---|
| `sent` | Le centre a gardé la notification ou un canal l'a acceptée, et il ne restait rien à essayer |
| `duplicate` | La même notification a été livrée au destinataire dans sa fenêtre ; rien n'a été fait |
| `rate_limited` | Le destinataire est au-delà de sa limite ; le centre l'a gardée |
| `failed` | Dans le mode `first_success`, aucun canal hors du centre ne l'a acceptée |

Chaque résultat est distribué comme un événement `NotificationDelivered`, qui porte l'identifiant du destinataire, et
journalisé — `info`, ou `warning` pour un échec — avec le type, le mode, le statut, les canaux et les raisons, jamais le
destinataire ni une adresse.

Un échec dont au moins un canal a été refusé ou sauté par un circuit ouvert lance `DeliveryFailed` après l'événement,
pour que Messenger retente le message du destinataire ; son message nomme le type et les canaux avec leurs raisons. Un
échec dont tous les canaux manquaient d'un contact, d'un expéditeur ou du consentement du destinataire ne lance rien :
réessayer n'y changerait rien. Un doublon ou un refus de la limite de débit ne lance rien non plus. Sans bus, un message
pour plusieurs destinataires livre à chacun d'eux, puis lance le premier échec, avant la diffusion, que la nouvelle
tentative publie.
