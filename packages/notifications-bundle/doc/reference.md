# Reference

## Domain

| Class | Role |
|---|---|
| `Domain\Model\Notification` | One notification of one recipient: references, state, count, collapse, read, archive |
| `Domain\Status\NotificationState` | `unread`, `read`, `archived` |
| `Domain\ValueObject\SubjectReference` | What it is about: a kind and an identifier |
| `Domain\ValueObject\NotificationData` | Up to sixteen keys naming integers or identifiers |
| `Domain\Identifiers` | The shapes of keys and identifiers |
| `Domain\Contract\NotificationRepository` | Where the centre's notifications are kept |
| `Domain\Exception\NotificationsException`, `InvalidNotification` | The bundle's exceptions |

## Application

| Class | Role |
|---|---|
| `Notifications` | The entry point |
| `NotificationType`, `NotificationTypes`, `Channels` | The declared types and the channel names |
| `Message\SendNotification`, `Handler\SendNotificationHandler` | The message of identifiers, split per recipient |
| `NotificationDispatcher` | The delivery to one recipient, in its type's mode, and the broadcast |
| `Delivery\DeliveryMode`, `DeliveryStatus`, `SkipReason`, `ChannelOutcome` | The modes, the statuses of a delivery, the reasons a channel is skipped, the answer of one attempt |
| `Delivery\DeliveryResult`, `NotificationDelivered` | The result of a delivery, and its event |
| `Delivery\DeliveryPolicy` | The channel senders, the window of duplicates, the rate limit and the circuit breakers of a delivery |
| `Delivery\DedupeTicket` | A duplicate, or the reservation of a delivery, as `DeliveryDedupe` gives it |
| `Delivery\RecipientRateLimit` | The limit of each recipient, on a limiter of symfony/rate-limiter |
| `OutgoingNotification` | A notification as Symfony Notifier sends it; on a chat channel, the title, a blank line and the body |
| `CheckedCurrentRecipient` | The application's current recipient, refused when it looks like an email address or is not an identifier |
| `NotificationRenderer`, `RenderedNotification`, `ResolvedSubject` | Writing a notification out |
| `WebPushMessage` | What a `WebPushSender` sends: the payload, the urgency, the topic and the time to live |
| `WebPush\PushSubscription` | A browser's push subscription: its endpoint, an HTTPS address of a public host, and its two keys |
| `WebPush\PushSubscriber` | Keeping and forgetting what a browser sent, its push service checked against those allowed |
| `Preferences\RecipientSettings`, `QuietHours`, `DigestFrequency`, `ConfiguredPreferences` | A recipient's choices |
| `Digests`, `Message\SendDigests`, `Message\SendDigest`, `Handler\DigestHandler` | The digests |
| `Retention`, `Message\PurgeNotifications`, `Handler\PurgeNotificationsHandler` | The purge |
| `RemoteNotification`, `ForwardingRemoteReceiver` | A notification of another site, and its default receiver |
| `Contract\*` | The ports: see [Extension points](extension-points.md) |
| `Exception\UnknownNotificationType`, `UnknownAudience` | An undeclared type, an unknown audience |
| `Exception\DeliveryFailed` | No channel reached the recipient, and a later attempt may |
| `Exception\InvalidPushSubscription` | A subscription that cannot be kept |

## Infrastructure

| Class | Role |
|---|---|
| `Doctrine\DbalNotificationRepository`, `DbalPreferences`, `DbalPushSubscriptions`, `NotificationTables`, `NotificationSchemaListener`, `StoredRow` | DBAL storage |
| `Memory\InMemoryNotificationRepository`, `InMemoryPushSubscriptions` | Storage in memory, reset between requests |
| `Mercure\MercureToasts`, `Http\FlashToasts`, `Http\NullToasts` | The toasts |
| `Http\CentreController`, `Http\CentreRequest`, `Http\Routes` | The centre's endpoints and their CSRF check |
| `Twig\CentreView`, `BellComponent`, `ListComponent`, `ToastsComponent`, `WebPushComponent` | The components |
| `Http\PushSubscriptionController` | The endpoints of Web Push |
| `WebPush\MinishlinkWebPushSender`, `WebPush\VapidKeys` | The bundle's Web Push sender on minishlink/web-push, and the VAPID keys it signs with |
| `Mailer\MailerDigestSender` | The digest through the transports of Symfony Mailer |
| `Idempotency\NotificationDedupe` | The `DeliveryDedupe` on phil/idempotency: one delivery of the same notification per window |
| `CircuitBreaker\ChannelBreakers` | The `ChannelCircuits` on phil/circuit-breaker: the circuits `notifications.<channel>[.<transport>]` |
| `Mailer\MailerChannelSender` | The `email` channel of the `first_success` mode, through the transports of Symfony Mailer |
| `Notifier\NotifierChannelSender` | The `sms`, `push` and `chat` channels of the `first_success` mode, through the transports of Notifier's texter and chatter |
| `Scheduler\NotificationSchedule` | The schedule `phil_notifications` |
| `Console\PurgeCommand` | `phil:notifications:purge` |
| `Console\VapidKeysCommand` | `phil:notifications:vapid-keys` |
| `Remote\RemoteNotificationController` | The endpoint of a network |
| `Security\NoCurrentRecipient` | No current recipient, when the application names none |
| `Notifier\EmptyRecipientDirectory`, `PlainTranslator` | The fallbacks |
| `DependencyInjection\NotificationsServices`, `NotificationsPass` | The services and their fallbacks |
| `PhilNotificationsBundle` | The bundle and its configuration |

## Services

`Notifications`, `NotificationRenderer`, `Digests`, `Retention`, `CentreView`, `CentreController` and the aliases
`NotificationRepository`, `NotificationPreferences` and `CurrentRecipient` are public; `DbalPreferences` too when
used. With `web_push` enabled, `PushSubscriber`, `PushSubscriptionController`, `MinishlinkWebPushSender` and the alias
`PushSubscriptions` are public.

## Assets

| File | Role |
|---|---|
| `assets/src/notifications_controller.js` | The Stimulus controller |
| `assets/src/lib/updates.js` | Reading an update, building a toast |
| `assets/src/web_push_controller.js`, `assets/src/lib/push.js` | The Web Push controller, and what it reads of the browser |
| `assets/worker/push-worker.js` | The service worker that shows the messages |
| `assets/styles/notifications.css` | The stylesheet, on PHIL UX's tokens |
| `config/routes.php`, `config/remote_routes.php`, `config/push_routes.php` | The routes |
| `templates/`, `translations/` | The templates, and the labels in English and French |
