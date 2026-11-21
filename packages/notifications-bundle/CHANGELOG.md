# Changelog

All notable changes to `phil/notifications-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.4.0-alpha.

### Changed

- The development requirements accept `phil/standard-webhooks-bundle` 0.2, the minor version of its main branch and no
  earlier one, so that the package installs on its own beside that bundle.
- `NotifierChannelSender` sends `sms`, `push` and `chat` through the transports of Notifier's texter and chatter, the
  services `texter.transports` and `chatter.transports`, instead of the texter and the chatter.

### Security

- The channels of the `first_success` mode never go through `MailerInterface`, the texter or the chatter, which put a
  message holding the address or phone number and the text on the bus when `framework.notifier.message_bus` is set or
  the message is routed: they send through the Mailer and Notifier transports, and a provider's refusal is known at once.

## 0.3.0-alpha - 2026-11-20

A type can try its channels one after the other until a provider accepts the notification, and every delivery can
hold back duplicates and count against a limit per recipient. `WebPushSender::send()`, `NotificationDispatcher::deliver()`
and the constructor of `MailerDigestSender` change. See [Upgrading from 0.2](doc/installation.md#upgrading-from-02).

### Added

- The `delivery` option of a type, `fan_out` by default or `first_success`, and `DeliveryMode`. In `first_success`,
  the channels outside the centre are tried in the order of the type, at once, inside the recipient's
  `SendNotification` message, until one is accepted by its provider; the centre, its toast and the broadcast channels
  stay outside the fallback.
- `ChannelSender`, the port of a channel sent at once, autoconfigured and asked before the bundle's senders:
  `NotifierChannelSender` for `sms`, `push` and `chat` through Notifier's texter and chatter, and `MailerChannelSender`
  for `email`, a plain text email through the transports of Symfony Mailer, from `delivery.from`. Only a
  `TransportExceptionInterface` of Notifier or Mailer, or a refusal of the push services, moves on to the next channel;
  any other exception goes up.
- `ChannelOutcome`: `Accepted`, `Unreachable` or `Refused`, the answer of one attempt.
- `SkipReason`: a channel skipped as `opted_out`, `quiet_hours`, `no_contact` — checked channel by channel against the
  directory's `EmailRecipientInterface` and `SmsRecipientInterface`, and the Web Push subscriptions —, `circuit_open`,
  `unavailable` or `refused`. An email left to the digest counts as accepted.
- `ChannelCircuits` and its adapter `ChannelBreakers`: with phil/circuit-breaker-bundle, the circuit `notifications.<channel>.<transport>` or
  `notifications.<channel>` guards its channel, skipped while open, a refusal recorded as a failure and an acceptance as
  a success.
- `DeliveryDedupe`, `DedupeTicket` and the adapter `NotificationDedupe`, with `delivery.dedupe`: the same notification is delivered to a recipient once per window, 300
  seconds by default or the type's `dedupe_ttl`, reserved atomically with phil/idempotency before the rate limit,
  freed after a refusal of the limit, a total failure or an exception; the centre keeps it once per window. The key is
  the message's `dedupeKey`, or its subject, data, actor and origin, with the type and the recipient.
- `SendNotification::$dedupeKey`, and the `dedupeKey` argument of `Notifications::send()` and `sendToAudience()`.
- `RecipientRateLimit` and `delivery.rate_limiter`: a limiter of symfony/rate-limiter, named in the configuration,
  spent once per recipient and delivery with a channel outside the centre; an `urgent` type passes, and a refusal
  leaves the notification in the centre.
- `delivery.single_server`. In the prod environment, a cache pool of the window or a limiter storage local to each
  server is refused, with `StoreLocality` and `RateLimitGuard` of phil/rate-limit, unless it is `true`.
- `DeliveryResult` — `sent`, `duplicate`, `rate_limited` or `failed`, the channels that took the notification, the
  channel `first_success` chose and the reason of each channel skipped —, dispatched as `NotificationDelivered` and
  logged without the recipient.
- `DeliveryFailed`, thrown last when no channel took the notification and one was refused or held back by its circuit,
  for Messenger to try again. Without a bus, a message for several recipients delivers to each of them first, and the
  broadcast waits for the retry.
- `Channels::reaches()` and `Channels::base()`.
- `DeliveryPolicy`, holding the channel senders, the window, the limit and the breakers of `NotificationDispatcher`.
- The documentation of the delivery: the modes, why `framework.notifier.message_bus` stays disabled, accepted meaning
  accepted by the provider, the window, the limit, the breakers and the result.

### Changed

- `WebPushSender::send()` returns a `ChannelOutcome`. `MinishlinkWebPushSender` answers `Accepted` when a push service
  took the message, `Unreachable` without a subscription or when every one was forgotten after a `404` or a `410`, and
  `Refused` otherwise.
- `NotificationDispatcher::deliver()` returns a `DeliveryResult`. The lines it logged for a channel without an address,
  without Notifier or without a Web Push sender are replaced by the one line of the result.
- `MailerDigestSender` sends through a Mailer `TransportInterface`, the service `mailer.transports`, rendered first by
  the optional `BodyRendererInterface`, instead of `MailerInterface`. The digest is removed without
  `mailer.transports`, as it was without `mailer`.
- The WebPush test ignores the notice the library logs when neither GMP nor BCMath is loaded, which depends on the PHP
  build.

### Security

- A digest no longer goes through `MailerInterface`, which put a `SendEmailMessage` holding the address and every line
  of the digest on the bus when the application routed it. The documentation says not to route `SendEmailMessage`,
  `SmsMessage` and `ChatMessage`, which hold addresses and text, and a test shows what they carry.
- `InvalidNotification` names a refused identifier, key or value by its length, never by its first characters, which
  could be an address in the logs or in a failed message.
- The log line of a delivery leaves the recipient out.

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
