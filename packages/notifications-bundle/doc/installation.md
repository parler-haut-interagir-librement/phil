# Installation

## Requirements

PHP 8.5 or later, Symfony 8.2 or later with FrameworkBundle, Symfony Notifier and Doctrine DBAL. Optional, each used
when it is installed:

| Package | For |
|---|---|
| `symfony/messenger` | Delivering notifications and digests in the background, one recipient per message |
| `symfony/mercure-bundle` | Pushing toasts and the unread count live |
| `symfony/ux-twig-component`, `symfony/stimulus-bundle` | The bell, list and toasts components, and their controller |
| `symfony/asset-mapper` | Serving the controller and the stylesheet without a build step |
| `symfony/mailer`, `symfony/twig-bridge` | The email digest |
| `symfony/scheduler` | Sending the digests and purging on schedule |
| `doctrine/orm` | Adding the tables to the schema the ORM generates |
| `phil/standard-webhooks-bundle` | Receiving the notifications of a network |
| `symfony/slack-notifier`, `symfony/discord-notifier` | The `chat/slack` and `chat/discord` channels |
| `symfony/ux-turbo` | Keeping the bell across Turbo page visits |
| `minishlink/web-push`, a PSR-7 implementation such as `nyholm/psr7`, `symfony/http-client` | The bundle's Web Push sender |
| `symfony/asset` | Giving the Web Push button the URL of the bundle's service worker |

An application that shows the centre to signed-in members names them by a stable identifier through a
`CurrentRecipient` service of its own: see [extension-points.md](extension-points.md).

## Enabling the bundle

```php
// config/bundles.php
Phil\NotificationsBundle\PhilNotificationsBundle::class => ['all' => true],
```

## The tables

With the ORM, the bundle adds its tables to the schema of the configured connection: `doctrine:migrations:diff` writes
the migration. `phil_notifications` holds the notifications, `phil_notification_preferences` the recipients' choices
when `preferences: dbal`, `phil_notification_push_subscriptions` the browsers subscribed with `web_push` enabled. Without the ORM, the application creates them from `NotificationTables::configure()`. Its
instants — created, changed, read, digested, subscribed — are `datetime_utc_immutable` columns: written and read in UTC whatever
the timezone of the PHP process or of the database server.

## The routes

The centre's endpoints, behind the application's firewall:

```yaml
# config/routes/phil_notifications.yaml
phil_notifications:
    resource: '@PhilNotificationsBundle/config/routes.php'
    prefix: /notifications
```

The endpoint of a network, outside the login firewall — its signature is the authentication:

```yaml
# config/routes/phil_notifications_remote.yaml
phil_notifications_remote:
    resource: '@PhilNotificationsBundle/config/remote_routes.php'
    prefix: /notifications
```

The endpoints of Web Push, with `web_push` enabled, behind the application's firewall:

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

The command writes a new pair of VAPID keys as two variables: the public key goes in the environment, the private one
in the application's secrets (`bin/console secrets:set PHIL_NOTIFICATIONS_VAPID_PRIVATE_KEY`). Then:

```yaml
phil_notifications:
    web_push:
        enabled: true
        subject: 'mailto:ops@example.com'
```

The button goes where members choose their notifications: `<twig:PhilNotifications:WebPush />`. See
[Channels](channels.md#web-push).

## Mercure

With MercureBundle registered, toasts are pushed to each recipient's private topic, `urn:phil:notifications:<id>`.
The application authorizes each recipient to subscribe to their own topic, through the authorization cookie of
MercureBundle; see [Notification centre](centre.md).

## The controller and the stylesheet

With AssetMapper, the bundle maps its controllers under `@phil/notifications-bundle`, its stylesheet under
`phil-notifications` and the service worker of Web Push under `phil-notifications-worker`:

```twig
<link rel="stylesheet" href="{{ asset('phil-notifications/notifications.css') }}">
```

The controllers — `notifications`, loaded at once, and `web-push`, loaded when its button is on the page — register
through `assets/controllers.json`, as any Symfony UX package.

## CSRF protection

The centre's forms are checked with FrameworkBundle's stateless CSRF protection, which the bundle configures: the
container stops with a message when `framework.csrf_protection` is false.
