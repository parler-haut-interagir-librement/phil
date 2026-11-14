<!-- phil-dev source-sha256=2f46830dac1204f3762582b7967ca4a342a999c9aed40c486df998a880bca153 -->
# Référence

## Domaine

| Classe | Rôle |
|---|---|
| `Domain\Model\Notification` | Une notification d'un destinataire : références, état, nombre, regroupement, lecture, archivage |
| `Domain\Status\NotificationState` | `unread`, `read`, `archived` |
| `Domain\ValueObject\SubjectReference` | Ce dont il s'agit : une sorte et un identifiant |
| `Domain\ValueObject\NotificationData` | Jusqu'à seize clés qui nomment des entiers ou des identifiants |
| `Domain\Identifiers` | Les formes des clés et des identifiants |
| `Domain\Contract\NotificationRepository` | L'endroit où sont gardées les notifications du centre |
| `Domain\Exception\NotificationsException`, `InvalidNotification` | Les exceptions du bundle |

## Application

| Classe | Rôle |
|---|---|
| `Notifications` | Le point d'entrée |
| `NotificationType`, `NotificationTypes`, `Channels` | Les types déclarés et les noms des canaux |
| `Message\SendNotification`, `Handler\SendNotificationHandler` | Le message d'identifiants, séparé par destinataire |
| `NotificationDispatcher` | La livraison à un destinataire, et la diffusion |
| `OutgoingNotification` | Une notification telle que Symfony Notifier l'envoie ; sur un canal de clavardage, le titre, une ligne vide et le corps |
| `CheckedCurrentRecipient` | Le destinataire courant de l'application, refusé quand il ressemble à une adresse courriel ou n'est pas un identifiant |
| `NotificationRenderer`, `RenderedNotification`, `ResolvedSubject` | La rédaction d'une notification |
| `WebPushMessage` | Ce qu'envoie un `WebPushSender` : la charge utile, l'urgence, le sujet et la durée de vie |
| `WebPush\PushSubscription` | L'abonnement d'un navigateur : son point de terminaison, une adresse HTTPS d'un hôte public, et ses deux clés |
| `WebPush\PushSubscriber` | Garder et oublier ce qu'un navigateur a envoyé, son service de poussée vérifié parmi ceux qui sont autorisés |
| `Preferences\RecipientSettings`, `QuietHours`, `DigestFrequency`, `ConfiguredPreferences` | Les choix d'un destinataire |
| `Digests`, `Message\SendDigests`, `Message\SendDigest`, `Handler\DigestHandler` | Les résumés |
| `Retention`, `Message\PurgeNotifications`, `Handler\PurgeNotificationsHandler` | La purge |
| `RemoteNotification`, `ForwardingRemoteReceiver` | Une notification d'un autre site, et son récepteur par défaut |
| `Contract\*` | Les ports : voir [Points d'extension](extension-points.md) |
| `Exception\UnknownNotificationType`, `UnknownAudience` | Un type non déclaré, un public inconnu |
| `Exception\InvalidPushSubscription` | Un abonnement qui ne peut pas être gardé |

## Infrastructure

| Classe | Rôle |
|---|---|
| `Doctrine\DbalNotificationRepository`, `DbalPreferences`, `DbalPushSubscriptions`, `NotificationTables`, `NotificationSchemaListener`, `StoredRow` | Le stockage DBAL |
| `Memory\InMemoryNotificationRepository`, `InMemoryPushSubscriptions` | Le stockage en mémoire, réinitialisé entre les requêtes |
| `Mercure\MercureToasts`, `Http\FlashToasts`, `Http\NullToasts` | Les messages éphémères |
| `Http\CentreController`, `Http\CentreRequest`, `Http\Routes` | Les points de terminaison du centre et leur vérification CSRF |
| `Twig\CentreView`, `BellComponent`, `ListComponent`, `ToastsComponent`, `WebPushComponent` | Les composants |
| `Http\PushSubscriptionController` | Les points de terminaison de Web Push |
| `WebPush\MinishlinkWebPushSender`, `WebPush\VapidKeys` | L'expéditeur Web Push du bundle sur minishlink/web-push, et les clés VAPID dont il signe |
| `Mailer\MailerDigestSender` | Le résumé par Symfony Mailer |
| `Scheduler\NotificationSchedule` | Le calendrier `phil_notifications` |
| `Console\PurgeCommand` | `phil:notifications:purge` |
| `Console\VapidKeysCommand` | `phil:notifications:vapid-keys` |
| `Remote\RemoteNotificationController` | Le point de terminaison d'un réseau |
| `Security\NoCurrentRecipient` | Aucun destinataire courant, quand l'application n'en nomme aucun |
| `Notifier\EmptyRecipientDirectory`, `PlainTranslator` | Les replis |
| `DependencyInjection\NotificationsServices`, `NotificationsPass` | Les services et leurs replis |
| `PhilNotificationsBundle` | Le bundle et sa configuration |

## Services

`Notifications`, `NotificationRenderer`, `Digests`, `Retention`, `CentreView`, `CentreController` et les alias
`NotificationRepository`, `NotificationPreferences` et `CurrentRecipient` sont publics ; `DbalPreferences` aussi quand
il est employé. Avec `web_push` activé, `PushSubscriber`, `PushSubscriptionController`, `MinishlinkWebPushSender` et
l'alias `PushSubscriptions` sont publics.

## Ressources

| Fichier | Rôle |
|---|---|
| `assets/src/notifications_controller.js` | Le contrôleur Stimulus |
| `assets/src/lib/updates.js` | Lire une mise à jour, construire un message éphémère |
| `assets/src/web_push_controller.js`, `assets/src/lib/push.js` | Le contrôleur Web Push, et ce qu'il lit du navigateur |
| `assets/worker/push-worker.js` | Le *service worker* qui affiche les messages |
| `assets/styles/notifications.css` | La feuille de style, sur les jetons de PHIL UX |
| `config/routes.php`, `config/remote_routes.php`, `config/push_routes.php` | Les routes |
| `templates/`, `translations/` | Les gabarits, et les libellés en anglais et en français |
