# Bounces

The bounce rules decide what a bounce or a complaint does to its address, whichever way it came: an event of a mail
provider's webhook, a raw message piped by the mail server, or a message of the bounce mailbox. Each input turns what
it receives into notices — one per address — and `BounceRules` decides each one.

## The rules

| Notice | Decision |
|---|---|
| A hard bounce: the mailbox does not exist | `Suspend` |
| A soft bounce below the threshold within the window | `Count` |
| The soft bounce that reaches the threshold within the window | `Suspend` |
| A complaint or a request to leave, from an authenticated sender | `Unsubscribe` |
| A complaint or a request to leave that no authenticator vouches for | `Review` |
| A report not understood, or one that names neither an address nor a recipient | `Review` |
| The same notice received again within the window | `Duplicate`, and nothing else |

The window slides: with a threshold of 3 and a window of `P30D`, the third soft bounce within thirty days suspends the
address. A suspension or an unsubscription forgets the soft bounces counted; a review keeps them.

A notice is named by its report and its address: the same report received again — a provider that retries its
webhook, a message piped twice — is a `Duplicate`. A report is named by the provider's event identifier, or by a hash
of the raw message.

A complaint unsubscribes only from an authenticated sender, because anyone can write an abuse report to the bounce
mailbox. An abuse report is authenticated when the configured authenticator vouches for it — by default
`AuthenticationResults` of `phil/bounce-parser`, which reads the DKIM result your own mail server recorded. An event of
a provider's webhook is authenticated: the Webhook component checked the provider's signature before the bundle sees
it.

## The event

Each decision but `Duplicate` is dispatched as a `BounceDecided` event. A newsletter, a contact list or the application
listens to it and applies the decision to its own records:

```php
use Phil\EmailAddressBundle\Domain\BounceDecision;
use Phil\EmailAddressBundle\Domain\Event\BounceDecided;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final readonly class ApplyBounceDecisions
{
    public function __construct(
        private Subscribers $subscribers,
    ) {
    }

    public function __invoke(BounceDecided $event): void
    {
        $subscriber = null !== $event->notice->recipientId
            ? $this->subscribers->get($event->notice->recipientId)
            : $this->subscribers->byFingerprint($event->notice->fingerprint);

        match ($event->decision) {
            BounceDecision::Suspend => $subscriber?->suspend(),
            BounceDecision::Unsubscribe => $subscriber?->unsubscribe(),
            BounceDecision::Review => $this->subscribers->flagForReview($subscriber),
            default => null,
        };
    }
}
```

The event carries the notice: its kind, its source, the address as the report gives it, the fingerprint of its
normalized form, the recipient identifier when the application signed one, and the soft bounces counted. It stays in
the process.

## The message

With `bounces.message_bus`, each decision is also sent on as a `BounceDecidedMessage`, for a handler in a worker. The
message carries identifiers only — the decision, the kind, the fingerprint, the recipient identifier and the notice's
key — never the address. The handler finds the address by its fingerprint, `EmailAddresses::fingerprint()` of the
addresses it keeps, or by the recipient identifier.

```yaml
framework:
    messenger:
        routing:
            Phil\EmailAddressBundle\Application\Message\BounceDecidedMessage: async
```

## A provider's webhook

Symfony's Webhook component receives the provider's request, checks its signature and parses it through the provider's
Mailer bridge into a `MailerDeliveryEvent` or a `MailerEngagementEvent`. Name the webhook in
`bounces.remote_event_consumers` and the bundle's `MailerEventConsumer` consumes its events:

```yaml
framework:
    webhook:
        routing:
            mailer_provider:
                service: 'mailer.webhook.request_parser.<provider>'
                secret: '%env(MAILER_WEBHOOK_SECRET)%'

phil_email_address:
    bounces:
        remote_event_consumers: [mailer_provider]
        recipient_metadata: recipient
```

| Event | Notice |
|---|---|
| `bounce`, `dropped` | hard |
| `deferred` | soft |
| `spam` | complaint, authenticated |
| `unsubscribe` | request to leave, authenticated |
| `received`, `delivered`, `open`, `click` | none |

With `recipient_metadata`, the recipient identifier is read from the metadata the application set on the message.

## A raw message

The mail server pipes each message of the bounce address to the command, which reads the standard input — or a file —,
parses it with `phil/bounce-parser` and prints one decision per line, never an address:

```text
# /etc/postfix/master.cf
bounces  unix  -  n  n  -  -  pipe
  user=app argv=/srv/app/bin/console phil:email-address:read-bounce
```

```sh
bin/console phil:email-address:read-bounce var/bounces/2026-11-20.eml
```

The command reads at most one byte past the parser's limit, which then reads what it can. It ends with success for
any message the parser read, so that the mail server does not bounce the bounce; it fails only when the file cannot be
read.

## The bounce mailbox

`BounceMailbox` is the port of the mailbox the bounces come back to: an adapter fetches the messages not handled yet
and acknowledges each one once its notices are decided. A message whose decision throws is not acknowledged and is
read again by the next poll.

```sh
bin/console phil:email-address:poll-mailbox --limit=100
```

The bundle ships `MaildirBounceMailbox`, for a mail server that delivers the bounce address to a Maildir: it reads
`new/`, the oldest first, and moves an acknowledged message to `cur/` with the « seen » flag.

```yaml
phil_email_address:
    bounces:
        maildir: '/var/mail/bounces'
```

The bundle reads no IMAP mailbox itself. A mailbox kept on another server reaches the Maildir through the tools
that already fetch mail: fetchmail or getmail polls it over IMAP and drops each message in the Maildir, and a Postfix
server delivers the bounce address to it by its local delivery (`home_mailbox = Maildir/`). The port `BounceMailbox`
is the place for an IMAP adapter in a separate package: it would plug in through `bounces.mailbox`, without changing
the commands or the rules.

## The return path

With `bounces.verp`, the bundle reads the tag of the return path each message was sent with, `bounces+TAG@example.net`.
A tag signed with the key — `Verp::encodeSigned()` of `phil/bounce-parser` — names the recipient: the notice carries
its identifier, and the rules count the recipient's soft bounces even when the report names no address. A tag that is
not signed with the key is ignored.
