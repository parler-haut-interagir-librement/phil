# Changelog

All notable changes to `phil/notifications-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.2.1-alpha.

Tests only.

### Changed

- The WebPush test ignores the notice the library logs when neither GMP nor BCMath is loaded, which depends on the PHP
  build.

## 0.2.0-alpha - 2026-11-18

Recipients, identifiers and keys of the three tables compare byte by byte on MySQL
and MariaDB. See [Upgrading from 0.1](doc/installation.md#upgrading-from-01).

### Changed

- `NotificationTables::configure()` takes the platform of the connection as its fourth argument, and the schema
  listener gives it: on MySQL and MariaDB, `id`, `recipient`, `type`, `actor`, `subject_type`, `subject_id` and
  `group_key` of `phil_notifications`, `recipient` of `phil_notification_preferences` and `id` and `recipient` of
  `phil_notification_push_subscriptions` take the `utf8mb4` character set and the `utf8mb4_bin` collation. PostgreSQL
  and SQLite keep their default, which compares bytes already.

### Security

- Under the default case-insensitive collation of MySQL and MariaDB, the recipient `member:ann` read, marked as read
  and deleted the notifications of `member:Ann`, shared their preferences and was sent to their push subscriptions:
  the store selects by recipient in SQL.

## 0.1.0-alpha - 2026-11-14

### Added

- `Notification`, made of references only — a type, a recipient, an actor, a `SubjectReference` and up to sixteen
  identifiers in `NotificationData` —, unread, read or archived, collapsing a later similar one into its count;
  `Identifiers` checks every key and identifier, and refuses spaces and `@`.
- `NotificationType` and `NotificationTypes`, declared under `phil_notifications.types`: each recipient's channels,
  the broadcast channels posted to once, collapsing, digest, toast, importance and translation keys; `Channels`.
- `Notifications::send()` and `sendToAudience()`, checking everything at the call; `SendNotification`, a Messenger
  message of identifiers split into one message per recipient by `SendNotificationHandler`; `AudienceProvider`.
- `NotificationDispatcher`: the centre with toasts, email left to the digest, quiet hours holding back what
  interrupts, channels turned off per type, Web Push through `WebPushSender`, the other channels through Symfony
  Notifier at the address of `RecipientDirectory`, and the broadcast channels; `OutgoingNotification`, whose chat
  message carries the title and the body.
- `WebPushMessage`, what a `WebPushSender` sends: the payload, kept within one encrypted record, the urgency from the
  type's importance, the topic of a collapsing type and the time to live.
- Web Push under `phil_notifications.web_push`: `PushSubscription`, an endpoint on a public HTTPS host and two keys;
  `PushSubscriber`, checking the push service against `push_services`; `PushSubscriptions`, with
  `DbalPushSubscriptions` and `InMemoryPushSubscriptions`, a bounded number of browsers per recipient;
  `MinishlinkWebPushSender` on minishlink/web-push, through Symfony HttpClient with private networks refused, forgetting
  the subscriptions a push service no longer knows; `VapidKeys` and `phil:notifications:vapid-keys`;
  `PushSubscriptionController` and `config/push_routes.php`; the `PhilNotifications:WebPush` component, the `web-push`
  Stimulus controller and the service worker `push-worker.js`.
- `NotificationRenderer`, writing a notification out when it is shown from its type's templates in the
  `phil_notifications` domain, with the subject resolved by `SubjectResolver` services.
- `NotificationPreferences` and `RecipientSettings` — digest, `QuietHours`, opt-outs, locale —, with
  `ConfiguredPreferences` and `DbalPreferences`.
- `NotificationRepository`, with `DbalNotificationRepository` — one indexed count for the bell, one update to
  collapse — and `InMemoryNotificationRepository`; `NotificationTables`, its instants in `datetime_utc_immutable`
  columns, and `NotificationSchemaListener`.
- `Digests`, `SendDigests`, `SendDigest` and `MailerDigestSender`, for the daily and weekly digests, whose
  `TrackingHeader` follows no opens nor clicks unless the recipient agreed to it through `EmailTrackingConsent`;
  `Retention`, `PurgeNotifications` and `phil:notifications:purge`; `NotificationSchedule` for Symfony Scheduler.
- `CurrentRecipient`, with no default: the application names its members by a stable identifier, and
  `CheckedCurrentRecipient` refuses one that looks like an email address; `NoCurrentRecipient` without one, and the
  container stops when Symfony Security is installed and the centre has none.
- The centre: `CentreController` and its routes, `CentreRequest` and its stateless CSRF token, `CentreView` and the
  `PhilNotifications:Bell`, `PhilNotifications:List` and `PhilNotifications:Toasts` components, with their templates,
  translations in English and French and a stylesheet on PHIL UX's tokens.
- `MercureToasts`, which logs a hub that cannot publish and does not fail the delivery, and `FlashToasts`, and the
  `notifications` Stimulus controller, which respects `prefers-reduced-motion`.
- `RemoteNotificationReceiver`, `ForwardingRemoteReceiver`, `RemoteNotification` and `RemoteNotificationController`,
  receiving the notifications of a network signed as Standard Webhooks.
- `PhilNotificationsBundle`, its configuration and `NotificationsPass`; with AssetMapperBundle, the controllers, the
  stylesheet and the service worker mapped in its `asset_mapper` extension.
- The benchmarks of the unread count and of the delivery.
- The documentation in `doc/`, one file per topic, indexed by the README; `CONTRIBUTING.md`, `SECURITY.md` and
  `CODE_OF_CONDUCT.md`.
