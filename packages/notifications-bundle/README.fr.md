<!-- phil-dev source-sha256=be5651c1459b220a0b7b59cb632440b65bb37696caef8223257acca04eabb60e -->
# phil/notifications-bundle

**Des notifications pour les applications Symfony : un centre avec des messages éphémères, le courriel et son résumé,
Web Push et le clavardage, selon le choix de chaque destinataire, faits d'identifiants seulement.**

phil/notifications-bundle permet à une application de dire à ses membres ce qui s'est passé : une réponse à leur
commentaire, une place libérée à un événement, un nouveau membre dans leur groupe. L'application déclare ses types de
notification (leurs canaux, si des notifications semblables se regroupent, leurs gabarits en anglais et en français ou
dans n'importe quelle locale) et en envoie une avec un type, les identifiants des destinataires, ce dont il s'agit et qui
l'a causé. Le bundle la livre sur Symfony Notifier aux canaux de chaque destinataire, selon son choix : le centre de
notifications propre au site, avec sa cloche, sa liste et ses messages éphémères ; le courriel, sur-le-champ ou dans un
résumé quotidien ou hebdomadaire ; Web Push, vers les navigateurs où ils l'ont activé ; le SMS ou la notification poussée par
n'importe quel transport de Notifier ; et un canal Slack ou Discord, une fois pour tout le monde. Les heures de silence
retiennent ce qui dérange, et chaque canal peut être désactivé par type.

Un type envoie sur tous ses canaux, ou les essaie l'un après l'autre — SMS, puis Web Push, puis courriel — jusqu'à ce
qu'un fournisseur accepte la notification, en sautant un canal sans adresse ou dont le disjoncteur est ouvert. La même
notification joint un destinataire une fois par fenêtre, chaque destinataire en reçoit un nombre limité, et chaque
livraison se termine par un résultat qui dit quel canal l'a prise et pourquoi les autres ont été sautés.

Une notification porte des références, jamais du contenu : un type, un destinataire, un acteur, un sujet
(`comment:981`) et quelques autres identifiants. Ce qu'elle dit est rédigé à partir des gabarits de son type quand elle
est affichée ou envoyée, le libellé et l'URL du sujet étant cherchés à ce moment par les résolveurs de l'application.
Ses messages Messenger sont aussi petits, et une notification envoyée à plusieurs devient un message par destinataire,
retenté seul.

Dans un réseau de sites, un site reçoit dans son propre centre les notifications qu'un autre envoie, signées selon
Standard Webhooks : un seul centre pour tout le réseau. L'envoi par une plateforme centrale ne fait pas partie de ce
bundle.

C'est un bundle Symfony sur Symfony Notifier et DBAL ; MercureBundle pousse en direct les messages éphémères et le
nombre de non-lues, et la session les garde pour la page suivante sans lui. Web Push est envoyé avec minishlink/web-push, derrière un port que
l'application peut remplir avec son propre expéditeur. Les formulaires du centre fonctionnent sans
JavaScript, et un contrôleur Stimulus les envoie en arrière-plan et affiche les messages éphémères, sans mouvement quand
le visiteur en demande moins.

## État

| Partie | État |
|---|---|
| `Notification`, `SubjectReference`, `NotificationData` : des références seulement, le regroupement, les états lu et archivé | Implémenté et testé |
| `NotificationType`, `NotificationTypes`, `Channels` : les types déclarés, les canaux, les canaux de diffusion, l'importance, les gabarits | Implémenté et testé |
| `Notifications`, `SendNotification` et son *handler*, `NotificationDispatcher` : le point d'entrée, la séparation par destinataire, la livraison sur chaque canal | Implémenté et testé |
| `NotificationRenderer`, `SubjectResolver`, `RecipientDirectory` : rédigé à l'affichage, traduit | Implémenté et testé |
| `NotificationPreferences` : résumé, heures de silence, canaux désactivés, locale ; `ConfiguredPreferences`, `DbalPreferences` | Implémenté et testé |
| `NotificationRepository` : `DbalNotificationRepository`, `InMemoryNotificationRepository`, les tables et l'écouteur de schéma | Implémenté et testé sur SQLite ; le nombre de non-lues et la livraison mesurés par banc d'essai |
| `Digests`, `MailerDigestSender` et son suivi désactivé sans `EmailTrackingConsent`, `NotificationSchedule`, `Retention`, `phil:notifications:purge` | Implémenté et testé |
| `MercureToasts`, `FlashToasts`, les points de terminaison du centre et ses composants Twig | Implémenté et testé |
| Le contrôleur Stimulus `notifications` | Implémenté ; son module testé sous Node |
| `RemoteNotificationReceiver` et son point de terminaison, sur phil/standard-webhooks-bundle | Implémenté et testé |
| `WebPushMessage` : la charge utile, l'urgence, le sujet et la durée de vie qu'envoie un expéditeur Web Push | Implémenté et testé |
| `WebPushSender` : le port du canal `web_push`, pour l'expéditeur du bundle ou celui de l'application | Implémenté et testé |
| `PushSubscription`, `PushSubscriber`, `PushSubscriptions` sur DBAL ou en mémoire : les navigateurs de chaque destinataire, des points de terminaison sur des services de poussée autorisés | Implémenté et testé sur SQLite |
| `MinishlinkWebPushSender` et `VapidKeys` : chiffré, signé et posté avec minishlink/web-push, abonnements expirés oubliés ; `phil:notifications:vapid-keys` | Implémenté et testé contre un faux service de poussée |
| `PushSubscriptionController`, le composant `PhilNotifications:WebPush`, le contrôleur `web-push` et le *service worker* | Implémenté et testé ; les modules et le *worker* sous Node |
| `DeliveryMode`, `ChannelSender`, `MailerChannelSender`, `NotifierChannelSender` : les canaux essayés tour à tour jusqu'à ce qu'un accepte, ou sur-le-champ | Implémenté et testé contre de faux transports |
| `NotificationDedupe`, `RecipientRateLimit`, `ChannelBreakers` : une livraison par fenêtre sur phil/idempotency, une limite par destinataire sur symfony/rate-limiter, des disjoncteurs sur phil/circuit-breaker | Implémenté et testé avec une horloge simulée, un cache en mémoire et un limiteur en mémoire |
| `DeliveryResult`, `NotificationDelivered`, `DeliveryFailed` : le résultat de chaque livraison, son événement, la nouvelle tentative d'un échec total | Implémenté et testé |
| `AudienceProvider` : des destinataires choisis par public | Un port, pour l'application ou un paquet de segments |
| Un résumé des nouvelles : les notifications d'une période réunies pour ceux qui ne les ont pas vues | Pas commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, les tables, les routes, Mercure, le contrôleur et la feuille de style. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Envoi](doc/fr/sending.md) | Les types, le point d'entrée, les destinataires et les publics, les références, Messenger. |
| [Canaux](doc/fr/channels.md) | Le centre, le courriel, Web Push et son *service worker*, les canaux de Notifier, les canaux de diffusion, les heures de silence et les refus. |
| [Livraison](doc/fr/delivery.md) | Tous les canaux ou le premier qui accepte, la fenêtre des doublons, la limite de débit, les disjoncteurs, le résultat. |
| [Préférences](doc/fr/preferences.md) | Ce que choisit un destinataire, où c'est gardé, et le port qui le remplace. |
| [Centre de notifications](doc/fr/centre.md) | Les composants, les points de terminaison, les messages éphémères, le contrôleur Stimulus, l'accessibilité. |
| [Résumé et conservation](doc/fr/digest.md) | Les résumés quotidiens et hebdomadaires, le calendrier, la purge. |
| [Réseau](doc/fr/network.md) | Recevoir les notifications des autres sites d'un réseau. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Les tests PHP et Node, les bancs d'essai et le seuil de mutation. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```sh
composer require phil/notifications-bundle
```

```yaml
# config/packages/phil_notifications.yaml
phil_notifications:
    recipient_directory: App\Notifications\Members    # a RecipientDirectory: addresses and names
    types:
        comment.replied:
            channels: [site, email]
            collapse: true
```

```php
$notifications->send('comment.replied', $author->id, new SubjectReference('comment', $reply->id), actor: $replier->id);
```

```twig
<twig:PhilNotifications:Bell />
<twig:PhilNotifications:Toasts />
```

## Prérequis

PHP 8.5 ou plus récent et Symfony 8.2 ou plus récent, avec Symfony Notifier, Doctrine DBAL et phil/rate-limit.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voyez
[LICENSE](LICENSE).
