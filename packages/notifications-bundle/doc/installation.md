# Installation

## Requirements

PHP 8.5 or later, Symfony 8.2 or later with FrameworkBundle, Symfony Notifier, Doctrine DBAL and phil/rate-limit,
which tells a store local to each server from a shared one. Optional, each used when it is installed:

| Package | For |
|---|---|
| `symfony/messenger` | Delivering notifications and digests in the background, one recipient per message |
| `symfony/mercure-bundle` | Pushing toasts and the unread count live |
| `symfony/ux-twig-component`, `symfony/stimulus-bundle` | The bell, list and toasts components, and their controller |
| `symfony/asset-mapper` | Serving the controller and the stylesheet without a build step |
| `symfony/mailer`, `symfony/twig-bridge` | The email digest, and the `email` channel of the `first_success` mode |
| `phil/idempotency`, with `symfony/lock` for a cache pool | One delivery of the same notification per window |
| `symfony/rate-limiter` | A limit of notifications per recipient |
| `phil/circuit-breaker-bundle` | Skipping a channel of the `first_success` mode while its provider's circuit is open |
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

### Collation of the keys

**Keys compare byte by byte**, as PHP compares them. The recipient decides whose notifications, preferences and
subscriptions a query reads: those of `member:Ann` are never read for `member:ann` or `member:ánn`, and a group never
gathers the notifications of two subjects whose identifiers differ only by case.

| Table | Columns in `utf8mb4_bin` on MySQL and MariaDB |
|---|---|
| `phil_notifications` | `id`, `recipient`, `type`, `actor`, `subject_type`, `subject_id`, `group_key` |
| `phil_notification_preferences` | `recipient` |
| `phil_notification_push_subscriptions` | `id`, `recipient` |

On MySQL and MariaDB, these columns take the `utf8mb4` character set and the `utf8mb4_bin` collation, given the
connection's platform: the default collation of MySQL 8, `utf8mb4_0900_ai_ci`, folds case and accents. PostgreSQL's
default collations are deterministic, equal only when the bytes are, and SQLite compares bytes: their columns keep the
default.

#### Upgrading from 0.1

The tables of a 0.1 install on MySQL or MariaDB kept the connection's collation. `doctrine:migrations:diff` writes the
migration that brings the columns above to `utf8mb4_bin`; run it with `doctrine:migrations:migrate`. The change only
makes values more distinct, so no existing row breaks a key. Rows that a case-insensitive collation had already merged
stay merged; values written from the migration on are kept apart. On PostgreSQL and SQLite the diff is empty.

## Upgrading from 0.3

- Each digest carries a signed link that stops it, in its footer, in its text part and in `List-Unsubscribe` with
  `List-Unsubscribe-Post: List-Unsubscribe=One-Click`, when a `DigestUnsubscriber` records it: the `dbal` preferences,
  or the service named in `digest.unsubscriber`. Import `digest_routes.php` outside the login firewall (see
  [The routes](#the-routes)) and set `framework.router.default_uri`, since the link is written in a worker; until the
  route is imported, digests leave without the link and an error is logged. `digest.unsubscribe: false` turns it off.
- With the `config` preferences and `defaults.digest` other than `never`, the container refuses to build unless
  `digest.unsubscriber` names a service or `digest.unsubscribe` is false: nobody could stop a digest sent to everyone.
- The digest has a text part, `@PhilNotifications/email/digest.txt.twig` (`digest.text_template`). An application that
  overrides `digest.html.twig` overrides it too, and reads `preferences_url` and `unsubscribe_url`, each null when
  there is none. `digest.preferences_route` names its page of notification preferences.
- `MailerDigestSender` takes a `DigestLinks` and the text template as its last two, optional, arguments.

## Upgrading from 0.2

- `WebPushSender::send()` returns a `ChannelOutcome`. A sender of the application's returns `Accepted` when a push
  service took the message, `Unreachable` when the recipient had no subscription, or only expired ones, and `Refused`
  otherwise.
- `NotificationDispatcher::deliver()` returns a `DeliveryResult`, and throws `DeliveryFailed` when a `first_success`
  type reached no channel and may later; a `fan_out` type never does. Code that only called it is unchanged.
- `MailerDigestSender` takes a Mailer `TransportInterface` — the service `mailer.transports` — instead of
  `MailerInterface`, and a `BodyRendererInterface` as its last argument. The digest no longer goes through a routed
  `SendEmailMessage`; an application that constructs the sender itself passes the transports.
- A notification that reaches no address is no longer logged by its own line naming the recipient: each delivery logs
  one line, without the recipient, and dispatches `NotificationDelivered`.
- A refused identifier, key or value is named by its length in the message of `InvalidNotification`, never quoted.
- The new options are off by default: `delivery` on a type is `fan_out`, and `delivery.dedupe` and
  `delivery.rate_limiter` are unset. See [Delivery](delivery.md) to turn them on.

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

The signed link that stops a digest, outside the login firewall — its signature is the authentication, and a mail
client posts to it without cookies:

```yaml
# config/routes/phil_notifications_digest.yaml
phil_notifications_digest:
    resource: '@PhilNotificationsBundle/config/digest_routes.php'
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
