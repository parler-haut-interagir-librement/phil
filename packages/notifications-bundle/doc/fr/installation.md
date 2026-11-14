<!-- phil-dev source-sha256=0398625018bd4c9efbbae4db501a43024da6f91853668d3695767f99cf252916 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2 ou plus récent avec FrameworkBundle, Symfony Notifier et Doctrine DBAL. Facultatifs,
chacun employé lorsqu'il est installé :

| Paquet | Pour |
|---|---|
| `symfony/messenger` | Livrer les notifications et les résumés en arrière-plan, un destinataire par message |
| `symfony/mercure-bundle` | Pousser en direct les messages éphémères et le nombre de non-lues |
| `symfony/ux-twig-component`, `symfony/stimulus-bundle` | Les composants de la cloche, de la liste et des messages éphémères, et leur contrôleur |
| `symfony/asset-mapper` | Servir le contrôleur et la feuille de style sans étape de construction |
| `symfony/mailer`, `symfony/twig-bridge` | Le résumé par courriel |
| `symfony/scheduler` | Envoyer les résumés et purger selon le calendrier |
| `doctrine/orm` | Ajouter les tables au schéma que génère l'ORM |
| `phil/standard-webhooks-bundle` | Recevoir les notifications d'un réseau |
| `symfony/slack-notifier`, `symfony/discord-notifier` | Les canaux `chat/slack` et `chat/discord` |
| `symfony/ux-turbo` | Garder la cloche d'une visite de page Turbo à l'autre |
| `minishlink/web-push`, une implémentation PSR-7 comme `nyholm/psr7`, `symfony/http-client` | L'expéditeur Web Push du bundle |
| `symfony/asset` | Donner au bouton Web Push l'URL du *service worker* du bundle |

Une application qui montre le centre à des membres connectés les nomme par un identifiant stable, au moyen d'un service
`CurrentRecipient` qui lui est propre : voir [Points d'extension](extension-points.md).

## Activer le bundle

```php
// config/bundles.php
Phil\NotificationsBundle\PhilNotificationsBundle::class => ['all' => true],
```

## Les tables

Avec l'ORM, le bundle ajoute ses tables au schéma de la connexion configurée : `doctrine:migrations:diff` écrit la
migration. `phil_notifications` contient les notifications, `phil_notification_preferences` les choix des
destinataires quand `preferences: dbal`, `phil_notification_push_subscriptions` les navigateurs abonnés avec `web_push`
activé. Sans l'ORM, l'application les crée à partir de `NotificationTables::configure()`. Ses instants — création,
modification, lecture, résumé, abonnement — sont des colonnes `datetime_utc_immutable` : écrites et lues en UTC,
quel que soit le fuseau horaire du processus PHP ou du serveur de base de données.

## Les routes

Les points de terminaison du centre, derrière le pare-feu de l'application :

```yaml
# config/routes/phil_notifications.yaml
phil_notifications:
    resource: '@PhilNotificationsBundle/config/routes.php'
    prefix: /notifications
```

Le point de terminaison d'un réseau, hors du pare-feu de connexion, puisque sa signature est l'authentification :

```yaml
# config/routes/phil_notifications_remote.yaml
phil_notifications_remote:
    resource: '@PhilNotificationsBundle/config/remote_routes.php'
    prefix: /notifications
```

Les points de terminaison de Web Push, avec `web_push` activé, derrière le pare-feu de l'application :

```yaml
# config/routes/phil_notifications_push.yaml
phil_notifications_push:
    resource: '@PhilNotificationsBundle/config/push_routes.php'
    prefix: /notifications
```

## Web Push

```sh
composer require minishlink/web-push nyholm/psr7 symfony/http-client
bin/console phil:notifications:vapid-keys
```

La commande écrit une nouvelle paire de clés VAPID sous forme de deux variables : la clé publique va dans
l'environnement, la privée dans les secrets de l'application
(`bin/console secrets:set PHIL_NOTIFICATIONS_VAPID_PRIVATE_KEY`). Puis :

```yaml
phil_notifications:
    web_push:
        enabled: true
        subject: 'mailto:ops@example.com'
```

Le bouton va là où les membres choisissent leurs notifications : `<twig:PhilNotifications:WebPush />`. Voir
[Canaux](channels.md#web-push).

## Mercure

Avec MercureBundle enregistré, les messages éphémères sont poussés vers le sujet privé de chaque destinataire,
`urn:phil:notifications:<id>`. L'application autorise chaque destinataire à s'abonner à son propre sujet, par le témoin
d'autorisation de MercureBundle ; voir [Centre de notifications](centre.md).

## Le contrôleur et la feuille de style

Avec AssetMapper, le bundle associe ses contrôleurs sous `@phil/notifications-bundle`, sa feuille de style sous
`phil-notifications` et le *service worker* de Web Push sous `phil-notifications-worker` :

```twig
<link rel="stylesheet" href="{{ asset('phil-notifications/notifications.css') }}">
```

Les contrôleurs — `notifications`, chargé d'emblée, et `web-push`, chargé quand son bouton est sur la page —
s'enregistrent par `assets/controllers.json`, comme tout paquet de Symfony UX.

## Protection CSRF

Les formulaires du centre sont vérifiés par la protection CSRF sans état de FrameworkBundle, que configure le bundle :
le conteneur s'arrête avec un message quand `framework.csrf_protection` vaut false.
