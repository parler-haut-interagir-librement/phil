# phil/notifications-bundle

**Notifications for Symfony applications: a centre with toasts, email and its digest, Web Push and chat, as each
recipient chose, made of identifiers only.**

phil/notifications-bundle lets an application tell its members what happened: a reply to their comment, a seat
freed at an event, a new member in their group. The application declares its notification types — their channels,
whether similar ones collapse, their templates in English and French or any locale — and sends one with a type, the
recipients' identifiers, what it is about and who caused it. The bundle delivers it on Symfony Notifier to each
recipient's channels, as they chose: the site's own notification centre, with its bell, its list and its toasts; email,
at once or in a daily or weekly digest; Web Push to the browsers they turned it on in; SMS or push through any Notifier
transport; and a Slack or Discord channel, once for everyone. Quiet hours hold back what interrupts, and each channel
can be turned off per type.

A type sends on all its channels, or tries them one after the other — SMS, then Web Push, then email — until a provider
accepts the notification, skipping a channel without an address or whose circuit breaker is open. The same
notification reaches a recipient once per window, each recipient gets a limited number, and each delivery ends with a
result that says which channel took it and why the others were skipped.

A notification carries references, never content: a type, a recipient, an actor, a subject (`comment:981`) and a few
more identifiers. What it says is written from its type's templates when it is shown or sent, the subject's label and
URL looked up by the application's resolvers at that moment. Its Messenger messages are as small, and a notification
sent to many becomes one message per recipient, retried alone.

In a network of sites, a site takes the notifications another one sends, signed as Standard Webhooks, into its own
centre: one centre for the whole network. Sending through a central platform is not part of this bundle.

It is a Symfony bundle on Symfony Notifier and DBAL; MercureBundle pushes the toasts and the unread count live, the
session keeps them for the next page without it. Web Push is sent with minishlink/web-push, behind a port an
application may fill with its own sender. The centre's forms work without JavaScript, and a Stimulus controller
sends them in the background and shows the toasts, without motion when the visitor asks for less.

## Status

| Part | State |
|---|---|
| `Notification`, `SubjectReference`, `NotificationData`: references only, collapsing, read and archived states | Implemented and tested |
| `NotificationType`, `NotificationTypes`, `Channels`: declared types, channels, broadcast channels, importance, templates | Implemented and tested |
| `Notifications`, `SendNotification` and its handler, `NotificationDispatcher`: the entry point, the split per recipient, the delivery on each channel | Implemented and tested |
| `NotificationRenderer`, `SubjectResolver`, `RecipientDirectory`: written out when shown, translated | Implemented and tested |
| `NotificationPreferences`: digest, quiet hours, channels turned off, locale; `ConfiguredPreferences`, `DbalPreferences` | Implemented and tested |
| `NotificationRepository`: `DbalNotificationRepository`, `InMemoryNotificationRepository`, the tables and the schema listener | Implemented and tested on SQLite; the unread count and the delivery benchmarked |
| `Digests`, `MailerDigestSender` and its tracking off without `EmailTrackingConsent`, `NotificationSchedule`, `Retention`, `phil:notifications:purge` | Implemented and tested |
| The digest's text part, its link to the preferences, and its signed link that stops it, one-click (RFC 8058): `DigestLinks`, `DigestUnsubscriber`, `DigestUnsubscribeController` | Implemented and tested |
| `MercureToasts`, `FlashToasts`, the centre's endpoints and its Twig components | Implemented and tested |
| The `notifications` Stimulus controller | Implemented; its module tested under Node |
| `RemoteNotificationReceiver` and its endpoint, on phil/standard-webhooks-bundle | Implemented and tested |
| `WebPushMessage`: the payload, urgency, topic and time to live a Web Push sender sends | Implemented and tested |
| `WebPushSender`: the port of the `web_push` channel, for the bundle's sender or the application's own | Implemented and tested |
| `PushSubscription`, `PushSubscriber`, `PushSubscriptions` on DBAL or in memory: each recipient's browsers, endpoints on allowed push services | Implemented and tested on SQLite |
| `MinishlinkWebPushSender` and `VapidKeys`: encrypted, signed and posted on minishlink/web-push, expired subscriptions forgotten; `phil:notifications:vapid-keys` | Implemented and tested against a mock push service |
| `PushSubscriptionController`, the `PhilNotifications:WebPush` component, the `web-push` controller and the service worker | Implemented and tested; the modules and the worker under Node |
| `DeliveryMode`, `ChannelSender`, `MailerChannelSender`, `NotifierChannelSender`: channels tried in turn until one accepts, at once | Implemented and tested against fake transports |
| `NotificationDedupe`, `RecipientRateLimit`, `ChannelBreakers`: one delivery per window on phil/idempotency, a limit per recipient on symfony/rate-limiter, circuit breakers on phil/circuit-breaker | Implemented and tested with a mock clock, an in-memory cache and an in-memory limiter |
| `DeliveryResult`, `NotificationDelivered`, `DeliveryFailed`: the result of each delivery, its event, the retry of a total failure | Implemented and tested |
| `AudienceProvider`: recipients chosen by audience | A port, for the application or a segments package |
| A news summary: the notifications of a period gathered for those who did not see them | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, enabling the bundle, the tables, the routes, Mercure, the controller and the stylesheet. |
| [Configuration](doc/configuration.md) | Every option, with an example. |
| [Sending](doc/sending.md) | Types, the entry point, recipients and audiences, references, Messenger. |
| [Channels](doc/channels.md) | The centre, email, Web Push and its service worker, Notifier channels, broadcast channels, quiet hours and opt-outs. |
| [Delivery](doc/delivery.md) | All channels or the first that accepts, the window of duplicates, the rate limit, circuit breakers, the result. |
| [Preferences](doc/preferences.md) | What a recipient chooses, where it is kept, and the port that replaces it. |
| [Notification centre](doc/centre.md) | The components, the endpoints, the toasts, the Stimulus controller, accessibility. |
| [Digest and retention](doc/digest.md) | The daily and weekly digests, the schedule, the purge. |
| [Network](doc/network.md) | Receiving the notifications of the other sites of a network. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | The PHP and Node tests, the benchmarks and the mutation bar. |
| [Reference](doc/reference.md) | Every class and service of the bundle. |

## Quick start

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

## Requirements

PHP 8.5 or later and Symfony 8.2 or later, with Symfony Notifier, Doctrine DBAL and phil/rate-limit.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
