# Delivery

What happens to a notification for one recipient once its message is handled: the window of duplicates, the rate
limit, the mode of its type — every channel, or one after the other until one accepts it —, and the result.

## Modes

A type chooses how its channels outside the centre are served:

| `delivery` | What it does |
|---|---|
| `fan_out` (default) | Hands the notification to every channel of the type the recipient did not turn off: Web Push through the `WebPushSender`, the other channels through Symfony Notifier, as Notifier itself sends a notification on all its channels |
| `first_success` | Tries the channels one after the other, in the order of the type's `channels`, and stops at the first whose provider accepts the notification |

```yaml
phil_notifications:
    types:
        account.password_changed:
            channels: [site, sms, web_push, email]
            delivery: first_success
            importance: urgent
```

In both modes, `site` keeps the notification in the centre and shows its toast before any other channel, and the
broadcast channels are posted to once, outside the fallback.

A provider that accepts a notification has taken it: **accepted means accepted by the provider**, not delivered to the
phone, the browser or the mailbox. Moving on to the next channel when a provider later reports the message undelivered
— a webhook of delivery status, a second attempt after a delay — is not part of the bundle.

## The `first_success` mode

### Attempts at once

Each channel is tried at once, inside the delivery of the recipient's `SendNotification` message, so that its
provider's answer is known before the next channel is considered:

| Channel | Sent through |
|---|---|
| `sms`, `sms/<transport>`, `push`, `push/<transport>` | The transports of Symfony Notifier's texter (`texter.transports`), when a texter transport is configured |
| `chat`, `chat/<transport>` | The transports of Symfony Notifier's chatter (`chatter.transports`), when a chatter transport is configured |
| `email`, `email/<transport>` | The transports of Symfony Mailer (`mailer.transports`), the transport picked by the `X-Transport` header: a plain text email, the title as its subject, from `delivery.from` or the address of the mailer's `headers` |
| `web_push` | The `WebPushSender` |

Every channel of this mode goes through transports, never through `MailerInterface`, the texter or the chatter, which
put the message on a bus when `framework.notifier.message_bus` is set or `SendEmailMessage` is routed: no
`SendEmailMessage`, `SmsMessage` or `ChatMessage` holding the recipient's address or phone number and the whole text
is queued, and the provider's answer is known before the fallback moves on. The `fan_out` mode goes through Symfony
Notifier itself, which does follow `framework.notifier.message_bus`: keep it disabled (`false`), and route
`SendNotification`, which carries identifiers only:

```yaml
framework:
    notifier:
        message_bus: false
    messenger:
        routing:
            Phil\NotificationsBundle\Application\Message\SendNotification: async
```

An application sends on a channel of its own — or on one of the above, in its own way — with a `ChannelSender`,
autoconfigured and asked before the bundle's senders; see [Extension points](extension-points.md#channelsender).

### What moves on to the next channel

Only a refusal of the provider:

- a `TransportExceptionInterface` of Symfony Notifier or of Symfony Mailer, which a sender catches and reports as
  `Refused`;
- a refusal of the push services, which the `WebPushSender` reports as `Refused`.

Any other exception is a fault of the application, not of the provider: it goes up as it is, and Messenger handles it.
A push service that answers `404` or `410` no longer knows the subscription: the subscription is forgotten, and the
channel counts as one without contact, not as an outage.

### Skipped channels

A channel is skipped, without being called, with its reason:

| Reason | When |
|---|---|
| `opted_out` | The recipient turned it off, for the type or for every type |
| `quiet_hours` | The recipient's quiet hours hold back the channels that interrupt, all but `site` and `email` |
| `no_contact` | The recipient directory gave nothing the channel needs, checked channel by channel: an `EmailRecipientInterface` with an address for email, an `SmsRecipientInterface` with a phone number for SMS; for Web Push, no subscription the push services still know |
| `circuit_open` | The circuit breaker of the channel is open |
| `unavailable` | No sender serves the channel: no Web Push sender, no texter, chatter or Mailer transport, no `ChannelSender` of the application's |
| `refused` | The provider refused the notification: the attempt was made, and the next channel is tried |

An email left to the recipient's digest counts as accepted: the digest takes the notification from the centre, and the
channels after `email` are not tried.

### Circuit breakers

With phil/circuit-breaker-bundle, a channel is guarded by the circuit
`notifications.<channel>.<transport>` — `notifications.chat.slack` for `chat/slack` — or else `notifications.<channel>`
— `notifications.sms` —, whichever the application declares first. A refusal is recorded as a failure of the circuit, an
acceptance as a success; an unreachable recipient says nothing of the provider and records nothing. While the circuit
is open, its channel is skipped; once it lets a probe through, the next notification is that probe.

```yaml
phil_circuit_breaker:
    cache_pool: cache.redis
    circuits:
        notifications.sms: { failure_rate_percent: 50, minimum_calls: 5, window_seconds: 60, open_seconds: 120 }
```

A channel whose circuit is not declared has no breaker. The circuit states must be shared by every server: the
circuit breaker bundle refuses a pool local to each server in the prod environment, unless its `single_server` option
says there is one.

## The window of duplicates

The same notification is delivered to a recipient once per window, five minutes by default, in both modes. Two
notifications are the same when their type and recipient match and either their `dedupeKey` matches, or, without one,
their subject, data, actor and origin do. The identifier a delivery gives a notification is never part of it: it is
new at each attempt.

```php
$notifications->send('event.reminder', $member->id, new SubjectReference('event', $event->id), dedupeKey: 'event:'.$event->id.':day-before');
```

```yaml
phil_notifications:
    delivery:
        dedupe:
            enabled: true
            ttl: 300                   # the window of a type that names none
            store: ~                   # an IdempotencyStore service of phil/idempotency, such as a DbalIdempotencyStore
            cache_pool: cache.redis    # or a cache pool, with lock_factory, for a CacheIdempotencyStore
            lock_factory: lock.factory
    types:
        event.reminder:
            dedupe_ttl: 86400          # null for delivery.dedupe.ttl, 0 for no window
```

The delivery is reserved with `IdempotencyGuard::begin()` of phil/idempotency, atomically, before the rate limit, so
that two workers handling the same message never both send it. A delivery that ends `sent` keeps the key for the
window; a refusal of the rate limit, a total failure or an exception frees it, so that the next attempt runs. The centre
keeps the notification once per window under a key of its own: a retry after a total failure does not add it to the
centre twice, nor show its toast again.

The store must be shared by every server, or each server would send once. A `cache_pool` local to each server — the
filesystem, APCu, array or system adapter of `symfony/cache`, as `cache.app` is by default — is refused in the prod
environment, unless `delivery.single_server` is `true`. The lock store of `lock_factory` must be shared as well.

## The rate limit

Each recipient gets a limited number of notifications outside the centre, counted by a limiter of
`symfony/rate-limiter` the application names — a sliding window, such as five an hour —, one token per delivery that
has a channel outside the centre to try. An `urgent` type passes whatever the count. A recipient past the limit still
gets the notification in the centre, with its toast; nothing else is sent, and the delivery ends `rate_limited`.

```yaml
framework:
    rate_limiter:
        notification_per_user:
            policy: sliding_window
            limit: 5
            interval: '1 hour'
            cache_pool: cache.redis

phil_notifications:
    delivery:
        rate_limiter: notification_per_user
```

The limiter's storage must be shared by every server: a local one is refused in the prod environment, unless
`delivery.single_server` is `true`. This limit counts notifications per recipient; it is not the limit of HTTP requests
of phil/rate-limit-bundle.

## The result

`NotificationDispatcher::deliver()` returns a `DeliveryResult`:

| Property | What it holds |
|---|---|
| `status` | `sent`, `duplicate`, `rate_limited` or `failed` |
| `mode` | The mode of the type |
| `channels` | The channels that took the notification, in order: `site`, an `email` left to the digest, the channel whose provider accepted it |
| `channel()` | The first of them outside the centre — the one `first_success` chose —, or null |
| `skipped` | The reason of each channel skipped, by channel |
| `type`, `recipient` | The type and the recipient's identifier |

| Status | When |
|---|---|
| `sent` | The centre kept the notification or a channel accepted it, and nothing was left to try |
| `duplicate` | The same notification was delivered to the recipient within its window; nothing was done |
| `rate_limited` | The recipient is past their limit; the centre kept it |
| `failed` | In the `first_success` mode, no channel outside the centre accepted it |

Each result is dispatched as a `NotificationDelivered` event, which carries the recipient's identifier, and logged —
`info`, or `warning` for a failure — with the type, the mode, the status, the channels and the reasons, never the
recipient nor an address.

A failure of which at least one channel was refused or skipped by an open circuit throws `DeliveryFailed` after the
event, so that Messenger tries the recipient's message again; its message names the type and the channels with their
reasons. A failure whose channels all lacked a contact, a sender or the recipient's consent throws nothing: trying
again would not change it. A duplicate or a refusal of the rate limit throws nothing either. Without a bus, a message
for several recipients delivers to each of them, then throws the first failure, before the broadcast, which the retry
posts.
