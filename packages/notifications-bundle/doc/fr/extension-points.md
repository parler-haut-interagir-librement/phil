<!-- phil-dev source-sha256=519a3e834e51cf7f8c510d740fb76b0cf91564badf34862520474d5824cbd994 -->
# Points d'extension

Ce qu'une application implémente, configure, remplace ou utilise. Chaque port est une interface d'un espace de noms
`Contract` ; chaque service qu'écrit l'application est nommé dans la configuration ou configuré automatiquement.

## Les ports écrits par l'application

### `RecipientDirectory`

`Phil\NotificationsBundle\Application\Contract\RecipientDirectory` (`recipient_directory`) : qui se trouve derrière un
identifiant, demandé quand une notification est envoyée ou affichée. Sans lui, le courriel, le SMS et le résumé sont
sautés.

| Méthode | Contrat |
|---|---|
| `contact(string $recipient): ?RecipientInterface` | Le destinataire de Notifier (`EmailRecipientInterface`, `SmsRecipientInterface`), ou `null` quand il est injoignable |
| `displayName(string $id): ?string` | Le nom affiché pour un acteur, ou `null` |

### `SubjectResolver`

`Phil\NotificationsBundle\Application\Contract\SubjectResolver` : configuré automatiquement, étiquette
`phil_notifications.subject_resolver`. Chaque résolveur répond pour les sortes de sujet qu'il connaît ; la première
réponse l'emporte.

| Méthode | Contrat |
|---|---|
| `resolve(SubjectReference $subject, ?string $locale): ?ResolvedSubject` | Son libellé et son URL, ou `null` pour une sorte qu'il ne connaît pas |

### `AudienceProvider`

`Phil\NotificationsBundle\Application\Contract\AudienceProvider` : configuré automatiquement, étiquette
`phil_notifications.audience_provider`. Le point d'extension d'un paquet de segments, qui choisit les destinataires par
règle.

| Méthode | Contrat |
|---|---|
| `supports(string $audience): bool` | S'il connaît le public |
| `recipients(string $audience): iterable<string>` | Les identifiants de ses destinataires, lus paresseusement |

### `WebPushSender`

`Phil\NotificationsBundle\Application\Contract\WebPushSender` (`web_push_sender`) : le canal `web_push` :
`MinishlinkWebPushSender` avec `web_push` activé, ou celui de l'application, qui peut lire les abonnements du bundle
dans `PushSubscriptions` ; voir [Canaux](channels.md#web-push).

| Méthode | Contrat |
|---|---|
| `send(string $recipient, RenderedNotification $notification): ChannelOutcome` | Envoie à chaque navigateur auquel le destinataire s'est abonné, `WebPushMessage::of()` donnant ce qu'il faut envoyer ; `Accepted` quand un service de poussée l'a pris, `Unreachable` sans abonnement qu'un service de poussée connaît encore, `Refused` autrement |

### `ChannelSender`

`Phil\NotificationsBundle\Application\Contract\ChannelSender` : envoie sur un canal sur-le-champ pour le mode
`first_success` ; voir [Livraison](delivery.md#le-mode-first_success). Un service qui l'implémente est autoconfiguré avec
l'étiquette `phil_notifications.channel_sender`, et interrogé avant les expéditeurs du bundle, `MailerChannelSender` et
`NotifierChannelSender`.

| Méthode | Contrat |
|---|---|
| `supports(string $channel): bool` | S'il envoie sur le canal, comme `sms` ou `chat/slack` |
| `send(string $channel, RenderedNotification $notification, NotificationType $type, ?RecipientInterface $contact): ChannelOutcome` | Envoie sur-le-champ : `Accepted` quand le fournisseur l'a pris, `Unreachable` quand le contact n'a pas ce que demande le canal, `Refused` quand le fournisseur l'a refusé ou n'a pu être joint — une `TransportExceptionInterface` de Notifier ou de Mailer, interceptée. Toute autre exception remonte |

### `EmailTrackingConsent`

`Phil\NotificationsBundle\Application\Contract\EmailTrackingConsent` (`digest.tracking_consent`) : si un destinataire a
accepté que les résumés qui lui sont envoyés soient suivis ; sans ce port, ni les ouvertures ni les clics ne le sont ;
voir [Résumé et conservation](digest.md#suivi-des-ouvertures-et-des-clics).

| Méthode | Contrat |
|---|---|
| `opens(string $recipient): bool` | Si les ouvertures peuvent être comptées |
| `clicks(string $recipient): bool` | Si les clics peuvent être suivis |

## Les ports avec des adaptateurs fournis

### `DeliveryDedupe`

`Phil\NotificationsBundle\Application\Contract\DeliveryDedupe` : une seule livraison de la même notification par fenêtre :
`NotificationDedupe` sur phil/idempotency avec `delivery.dedupe` activé ; voir
[Livraison](delivery.md#la-fenêtre-des-doublons).

| Méthode | Contrat |
|---|---|
| `begin(SendNotification $message, NotificationType $type, string $recipient): ?DedupeTicket` | Réserve la livraison de façon atomique, ou donne un billet de doublon ; null quand le type n'a pas de fenêtre |
| `complete(DedupeTicket $ticket, NotificationType $type): void` | Garde la clé pour la fenêtre |
| `release(DedupeTicket $ticket, NotificationType $type): void` | Laisse la tentative suivante s'exécuter |
| `once(SendNotification $message, NotificationType $type, string $recipient, Closure $record): bool` | Exécute `$record` une fois par clé et par fenêtre, sous une clé qui lui est propre : le centre |

### `ChannelCircuits`

`Phil\NotificationsBundle\Application\Contract\ChannelCircuits` : les disjoncteurs des canaux du mode `first_success` :
`ChannelBreakers` sur phil/circuit-breaker quand il est installé ; voir [Livraison](delivery.md#les-disjoncteurs).

| Méthode | Contrat |
|---|---|
| `isOpen(string $channel): bool` | Si le canal est retenu ; un canal sans circuit ne l'est jamais |
| `recordSuccess(string $channel): void`, `recordFailure(string $channel): void` | Ce que le fournisseur du canal a répondu |

### `NotificationPreferences`

`Phil\NotificationsBundle\Application\Contract\NotificationPreferences` (`preferences`) : `ConfiguredPreferences`,
`DbalPreferences`, ou celui de l'application, par exemple un qui s'appuie sur un paquet de réglages ; voir
[Préférences](preferences.md).

| Méthode | Contrat |
|---|---|
| `settingsFor(string $recipient): RecipientSettings` | Le résumé, les heures de silence, les refus et la locale du destinataire ; rapide |

### `CurrentRecipient`

`Phil\NotificationsBundle\Application\Contract\CurrentRecipient` (`current_recipient`) : celui de l'application, sans
valeur par défaut : un membre est nommé par l'identifiant stable auquel les notifications sont envoyées, comme l'id de
son compte, jamais par l'identifiant d'utilisateur de Symfony Security, qui peut être une adresse courriel. Sans lui, le
centre ne montre les notifications de personne ; avec Symfony Security installé et les routes du centre enregistrées, le
conteneur s'arrête et le dit.

| Méthode | Contrat |
|---|---|
| `id(): ?string` | Le centre de qui la requête montre, ou `null` pour un visiteur |

Le bundle le lit par `CheckedCurrentRecipient`, qui refuse avec `InvalidNotification` un identifiant qui contient un
`@`, comme une adresse courriel, ou qui n'est pas un identifiant d'`Identifiers`.

```php
final readonly class MemberRecipient implements CurrentRecipient
{
    public function __construct(private Security $security) {}

    public function id(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof Member ? $user->id()->toRfc4122() : null;
    }
}
```

### `ToastPublisher`

`Phil\NotificationsBundle\Application\Contract\ToastPublisher` : `MercureToasts`, `FlashToasts` ou `NullToasts`, selon
`toasts.transport` ; une application fait pointer l'alias vers un autre service de poussée.

| Méthode | Contrat |
|---|---|
| `publish(RenderedNotification $notification, int $unread): void` | Affiche le message éphémère à son destinataire, avec son nombre de non-lues ; ne fait jamais échouer la livraison |

### `DigestSender`

`Phil\NotificationsBundle\Application\Contract\DigestSender` (`digest.sender`) : `MailerDigestSender` par défaut.

| Méthode | Contrat |
|---|---|
| `send(RecipientInterface $contact, DigestFrequency $frequency, non-empty-list<RenderedNotification> $notifications, ?string $locale): void` | Envoie un résumé |

### `DigestUnsubscriber`

`Phil\NotificationsBundle\Application\Contract\DigestUnsubscriber` (`digest.unsubscriber`) : `DbalPreferences` avec les
préférences `dbal` ; aucun avec les autres, et un résumé ne porte alors aucun lien de désinscription. Une application qui
garde elle-même les réglages de ses membres l'implémente à côté de son `NotificationPreferences`.

| Méthode | Contrat |
|---|---|
| `unsubscribe(string $recipient): void` | Le résumé du destinataire devient `never`, le reste de ses réglages gardé ; deux fois ne change rien |

### `RemoteNotificationReceiver`

`Phil\NotificationsBundle\Application\Contract\RemoteNotificationReceiver` (`remote.receiver`) :
`ForwardingRemoteReceiver` par défaut ; voir [Réseau](network.md).

| Méthode | Contrat |
|---|---|
| `receive(RemoteNotification $notification): void` | Reçoit une notification vérifiée ; lance `UnknownNotificationType` pour un type qu'il n'accepte pas |

### `PushSubscriptions`

`Phil\NotificationsBundle\Application\Contract\PushSubscriptions` (`store`, avec `web_push` activé) :
`DbalPushSubscriptions` ou `InMemoryPushSubscriptions`, qui gardent au plus `web_push.max_subscriptions` navigateurs par
destinataire, les derniers ; l'application pointe l'alias vers son propre stockage.

| Méthode | Contrat |
|---|---|
| `subscribe(string $recipient, PushSubscription $subscription): void` | Garde l'abonnement, son point de terminaison passant à ce destinataire, et oublie les plus anciens au-delà de la limite |
| `of(string $recipient): list<PushSubscription>` | Les abonnements du destinataire, les derniers d'abord |
| `unsubscribe(string $recipient, string $endpoint): bool` | Oublie le point de terminaison si le destinataire l'a abonné |
| `forget(string $endpoint): void` | Oublie un point de terminaison que son service de poussée ne connaît plus |
| `forgetRecipient(string $recipient): void` | Oublie chaque abonnement d'un destinataire qui part |

### `NotificationRepository`

`Phil\NotificationsBundle\Domain\Contract\NotificationRepository` (`store`) : `DbalNotificationRepository` ou
`InMemoryNotificationRepository`. Chaque lecture et chaque modification d'un centre nomme son destinataire ; `record()`
regroupe dans une notification semblable non lue quand on le lui demande, et les méthodes du résumé et de la purge
travaillent une page à la fois.

## Les services qu'appelle l'application

| Service | Usage |
|---|---|
| `Notifications` | `send()`, `sendToAudience()` |
| `NotificationRenderer` | Rédiger une notification, pour une page propre à l'application |
| `Digests` | `sweep()`, `send()` hors du calendrier |
| `Retention` | `purge()` hors du calendrier |
| `DbalPreferences` | `save()`, `forget()` avec `preferences: dbal` |
| `PushSubscriptions` | `forgetRecipient()` quand un membre part, avec `web_push` activé |

## Événements

`Application\Delivery\NotificationDelivered`, distribué sur le répartiteur d'événements de l'application après chaque
livraison à un destinataire, quel que soit son statut, porte le `DeliveryResult` : le type, l'identifiant du
destinataire, le mode, le statut, les canaux qui ont pris la notification et la raison de chaque canal sauté. Un
écouteur compte, audite ou alerte ; il ne reçoit jamais d'adresse.

## Messages

`SendNotification`, `SendDigests`, `SendDigest` et `PurgeNotifications` ne portent que des identifiants ; l'application
les achemine comme elle le souhaite. Elle laisse `SendEmailMessage`, `SmsMessage` et `ChatMessage` sans acheminement :
ils contiennent des adresses et du texte ; voir [Envoi](sending.md#messenger).

## Gabarits et traductions

`@PhilNotifications/components/Bell.html.twig`, `List.html.twig`, `Toasts.html.twig`, `WebPush.html.twig`, `_list.html.twig`,
`_toast.html.twig`, `email/digest.html.twig`, `email/digest.txt.twig` et `digest/unsubscribe.html.twig` se redéfinissent
sous `templates/bundles/PhilNotificationsBundle/`. Le
domaine `phil_notifications` contient les gabarits des types, qu'écrit l'application, et les libellés du centre, fournis
en anglais et en français.

## Stabilité

Le bundle est en alpha : un point d'extension peut changer dans une version mineure, ce que le journal des changements et
[Installation](installation.md) disent comment suivre.
