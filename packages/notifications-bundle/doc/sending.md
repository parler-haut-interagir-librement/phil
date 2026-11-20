# Sending

## Types

A type is declared under `phil_notifications.types`, by a key — lowercase letters, digits, `_`, `.` and `-`. Its
templates are translation keys of the `phil_notifications` domain, `<key>.title` and `<key>.body` by default, which
the application translates in each locale:

```yaml
# translations/phil_notifications+intl-icu.fr.yaml
comment.replied.title: '{actor} a répondu à {subject}'
comment.replied.body: '{count, plural, one {Une réponse} other {# réponses}}'
```

Each template receives `%actor%` — the actor's name from the directory —, `%subject%` — the subject's label from its
resolver —, `%count%` — how many notifications it stands for after collapsing — and each data entry by its key.
With an ICU domain, the same values are named without the percent signs.

## The entry point

```php
use Phil\NotificationsBundle\Application\Notifications;
use Phil\NotificationsBundle\Domain\ValueObject\SubjectReference;

$notifications->send(
    'comment.replied',
    [$author->id],                              // a string or a list, up to 1 000
    new SubjectReference('comment', $reply->id),
    ['event' => $event->id],                    // identifiers only
    actor: $replier->id,
);

$notifications->sendToAudience('event.cancelled', 'event:'.$event->id.':attendees', new SubjectReference('event', $event->id));

$notifications->send('event.reminder', $member->id, new SubjectReference('event', $event->id), dedupeKey: 'event:'.$event->id.':day-before');
```

A `dedupeKey`, an identifier, makes two notifications the same within the window of duplicates when their subject and
data are not enough; see [Delivery](delivery.md#the-window-of-duplicates).

Everything is checked at the call: an undeclared type is an `UnknownNotificationType`, an identifier of the wrong shape
an `InvalidNotification`. An identifier holds letters, digits, `_`, `.`, `:` and `-`, up to 190 characters: never a
space nor an `@`, so that no name, sentence or address travels with a notification.

## Audiences

An audience is a name the application gives a group of recipients. An `AudienceProvider` service — autoconfigured —
turns it into identifiers when the notification is delivered, so that the message carries the name only:

```php
final readonly class Attendees implements AudienceProvider
{
    public function supports(string $audience): bool { return str_starts_with($audience, 'event:'); }

    public function recipients(string $audience): iterable { /* yields identifiers, a page at a time */ }
}
```

An audience no provider supports fails the delivery with `UnknownAudience`.

## Messenger

The message `SendNotification` holds the type, the recipients or the audience, the subject's type and identifier, the
data, the actor, the origin and the key of duplicates. With a bus, a message for several recipients — or an audience — is split into one
message per recipient, so that a failure is retried for that recipient alone; the broadcast channels are posted to by
the first message only. The routing is the application's: sent to an asynchronous transport, notifications are
delivered by a worker.

```yaml
framework:
    messenger:
        routing:
            Phil\NotificationsBundle\Application\Message\SendNotification: async
```

A delivery that reached no channel and may succeed later throws `DeliveryFailed`, and the worker tries the message
again; see [Delivery](delivery.md#the-result).

Route `SendNotification` only. The bundle's own senders — the email, SMS, push and chat channels of the
`first_success` mode and the digest — send through the transports of Symfony Mailer (`mailer.transports`) and of
Notifier's texter and chatter (`texter.transports`, `chatter.transports`), never through `MailerInterface`, the texter
or the chatter: whatever the application routes and whatever `framework.notifier.message_bus` says, they put no
address, phone number or rendered text in a Messenger queue, and a provider's refusal is known when they return.

The `fan_out` mode hands the other channels to Symfony Notifier itself, whose channels follow the application's
configuration: with `framework.notifier.message_bus` set and `SendEmailMessage`, `SmsMessage` or `ChatMessage` routed
to a transport, the recipient's address or phone number and the whole text wait in the queue, and in its failure
transport, for as long as the message stays there. Leave those messages unrouted and keep
`framework.notifier.message_bus` disabled. An application that routes them anyway for its own messages keeps personal
information in its Messenger transport and its failure transport: it needs a retention rule for the failure transport
— `messenger:failed:show` to look, `messenger:failed:remove` to purge — and a word in its privacy policy.

Every service of the delivery holds no state between messages, as a worker needs.
