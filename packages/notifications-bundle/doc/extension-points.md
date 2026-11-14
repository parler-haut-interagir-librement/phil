# Extension points

What an application implements, configures, replaces or relies on. Every port is an interface of a `Contract`
namespace; each service the application writes is named in the configuration or autoconfigured.

## Ports written by the application

### `RecipientDirectory`

`Phil\NotificationsBundle\Application\Contract\RecipientDirectory` (`recipient_directory`) — who stands behind an
identifier, asked when a notification is sent or shown. Without one, email, SMS and the digest are skipped.

| Method | Contract |
|---|---|
| `contact(string $recipient): ?RecipientInterface` | The Notifier recipient — `EmailRecipientInterface`, `SmsRecipientInterface` —, or `null` when unreachable |
| `displayName(string $id): ?string` | The name shown for an actor, or `null` |

### `SubjectResolver`

`Phil\NotificationsBundle\Application\Contract\SubjectResolver` — autoconfigured, tag
`phil_notifications.subject_resolver`. Each resolver answers for the kinds of subject it knows; the first answer wins.

| Method | Contract |
|---|---|
| `resolve(SubjectReference $subject, ?string $locale): ?ResolvedSubject` | Its label and URL, or `null` for a kind it does not know |

### `AudienceProvider`

`Phil\NotificationsBundle\Application\Contract\AudienceProvider` — autoconfigured, tag
`phil_notifications.audience_provider`. The extension point of a segments package, which chooses recipients by rule.

| Method | Contract |
|---|---|
| `supports(string $audience): bool` | Whether it knows the audience |
| `recipients(string $audience): iterable<string>` | Its recipients' identifiers, read lazily |

### `WebPushSender`

`Phil\NotificationsBundle\Application\Contract\WebPushSender` (`web_push_sender`) — the `web_push` channel:
`MinishlinkWebPushSender` with `web_push` enabled, or the application's own, which may read the bundle's subscriptions
from `PushSubscriptions`; see [Channels](channels.md#web-push).

| Method | Contract |
|---|---|
| `send(string $recipient, RenderedNotification $notification): void` | Sends to each browser the recipient subscribed, `WebPushMessage::of()` giving what to send |

### `EmailTrackingConsent`

`Phil\NotificationsBundle\Application\Contract\EmailTrackingConsent` (`digest.tracking_consent`) — whether a
recipient agreed that the digests sent to them be followed; without one, no opens nor clicks are; see
[Digest and retention](digest.md#open-and-click-tracking).

| Method | Contract |
|---|---|
| `opens(string $recipient): bool` | Whether the opens may be counted |
| `clicks(string $recipient): bool` | Whether the clicks may be followed |

## Ports with shipped adapters

### `NotificationPreferences`

`Phil\NotificationsBundle\Application\Contract\NotificationPreferences` (`preferences`) — `ConfiguredPreferences`,
`DbalPreferences`, or the application's own, such as one backed by a settings package; see
[Preferences](preferences.md).

| Method | Contract |
|---|---|
| `settingsFor(string $recipient): RecipientSettings` | The recipient's digest, quiet hours, opt-outs and locale; fast |

### `CurrentRecipient`

`Phil\NotificationsBundle\Application\Contract\CurrentRecipient` (`current_recipient`) — the application's own, with
no default: a member is named by the stable identifier notifications are sent to, such as the id of their account,
never by the user identifier of Symfony Security, which can be an email address. Without one, the centre shows no
one's notifications; with Symfony Security installed and the centre's routes registered, the container stops and says
so.

| Method | Contract |
|---|---|
| `id(): ?string` | Whose centre the request shows, or `null` for a visitor |

The bundle reads it through `CheckedCurrentRecipient`, which refuses with `InvalidNotification` an identifier that
holds an `@`, as an email address does, or that is not an identifier of `Identifiers`.

```php
final readonly class MemberRecipient implements CurrentRecipient
{
    public function __construct(private Security $security) {}

    public function id(): ?string
    {
        $user = $this->security->getUser();

        return $user instanceof Member ? $user->id()->toRfc4122() : null;
    }
}
```

### `ToastPublisher`

`Phil\NotificationsBundle\Application\Contract\ToastPublisher` — `MercureToasts`, `FlashToasts` or `NullToasts`, by
`toasts.transport`; an application points the alias at another push service.

| Method | Contract |
|---|---|
| `publish(RenderedNotification $notification, int $unread): void` | Shows the toast to its recipient, with their unread count; never fails the delivery |

### `DigestSender`

`Phil\NotificationsBundle\Application\Contract\DigestSender` (`digest.sender`) — `MailerDigestSender` by default.

| Method | Contract |
|---|---|
| `send(RecipientInterface $contact, DigestFrequency $frequency, non-empty-list<RenderedNotification> $notifications, ?string $locale): void` | Sends one digest |

### `RemoteNotificationReceiver`

`Phil\NotificationsBundle\Application\Contract\RemoteNotificationReceiver` (`remote.receiver`) —
`ForwardingRemoteReceiver` by default; see [Network](network.md).

| Method | Contract |
|---|---|
| `receive(RemoteNotification $notification): void` | Takes in a verified notification; throws `UnknownNotificationType` for a type it does not take |

### `PushSubscriptions`

`Phil\NotificationsBundle\Application\Contract\PushSubscriptions` (`store`, with `web_push` enabled) —
`DbalPushSubscriptions` or `InMemoryPushSubscriptions`, which keep at most `web_push.max_subscriptions` browsers per
recipient, the latest; an application points the alias at its own store.

| Method | Contract |
|---|---|
| `subscribe(string $recipient, PushSubscription $subscription): void` | Keeps the subscription, its endpoint moving to this recipient, and forgets the oldest beyond the limit |
| `of(string $recipient): list<PushSubscription>` | The recipient's subscriptions, the latest first |
| `unsubscribe(string $recipient, string $endpoint): bool` | Forgets the endpoint if the recipient subscribed it |
| `forget(string $endpoint): void` | Forgets an endpoint its push service no longer knows |
| `forgetRecipient(string $recipient): void` | Forgets every subscription of a recipient who leaves |

### `NotificationRepository`

`Phil\NotificationsBundle\Domain\Contract\NotificationRepository` (`store`) — `DbalNotificationRepository` or
`InMemoryNotificationRepository`. Every read and change of a centre names its recipient; `record()` collapses into a
similar unread notification when asked, and the digest and purge methods work a page at a time.

## Services the application calls

| Service | Use |
|---|---|
| `Notifications` | `send()`, `sendToAudience()` |
| `NotificationRenderer` | Writing a notification out, for a page of the application's own |
| `Digests` | `sweep()`, `send()` outside the schedule |
| `Retention` | `purge()` outside the schedule |
| `DbalPreferences` | `save()`, `forget()` with `preferences: dbal` |
| `PushSubscriptions` | `forgetRecipient()` when a member leaves, with `web_push` enabled |

## Messages

`SendNotification`, `SendDigests`, `SendDigest` and `PurgeNotifications` carry identifiers only; the application
routes them as it wishes.

## Templates and translations

`@PhilNotifications/components/Bell.html.twig`, `List.html.twig`, `Toasts.html.twig`, `WebPush.html.twig`, `_list.html.twig`,
`_toast.html.twig` and `email/digest.html.twig` can be overridden under `templates/bundles/PhilNotificationsBundle/`.
The `phil_notifications` domain holds the types' templates, which the application writes, and the centre's labels,
shipped in English and French.

## Stability

The bundle is not released yet: every extension point may change until its first version.
