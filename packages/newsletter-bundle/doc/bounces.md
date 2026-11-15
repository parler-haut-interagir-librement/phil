# Bounces

A bounce changes the subscriber it is about:

| Bounce | Effect |
|---|---|
| Hard: no such mailbox, domain gone | The address is suppressed: `bounced`, never mailed again |
| Soft: mailbox full, server busy | Counted; at `bounces.soft_threshold`, three by default, the address is suppressed; a message delivered resets the count |
| Complaint: an abuse report | The address is suppressed: `complained` |
| A request to leave | The subscriber is unsubscribed |
| An automatic reply, anything else | Nothing |

A suppressed address is kept in `phil_newsletter_suppression` as an HMAC of the site and the address under the
bundle's key: it survives the erasure of the subscriber without keeping the address. Each change is announced by an
`AudienceChanged` event, `bounced` or `complained`.

## The bounce mailbox

With `bounces.imap`, the schedule `newsletter` reads the mailbox every five minutes, and
`phil:newsletter:fetch-bounces` reads it at once. The bundle's IMAP client logs in over implicit TLS, the certificate
checked, reads each unread message without marking it, hands it to `phil/bounce-parser` — delivery status
notifications, abuse reports, bounces written as text, automatic replies —, then marks it read, or deleted with
`delete: true`. Every line and every message read is bounded.

The client speaks implicit TLS only, on port 993 by default, as RFC 8314 recommends: the connection is encrypted from
its first byte, and nothing is ever sent in clear. It does not speak STARTTLS on port 143, which an attacker on the path
can strip when a client does not demand it; every large mail provider offers port 993. A mailbox that offers only
STARTTLS is read by a `BounceMailbox` of the application's (see [Extension points](extension-points.md)).

With `bounces.verp`, each message's return path is `bounces+<delivery>.<signature>@example.com`: the bounce names its
delivery, signed so that nobody can bounce another subscriber, and the subscriber is found even when the report names
another address. Without it, the subscriber is found by the failed address on the current site.

## Providers' webhooks

A provider that posts its events — through Symfony's Webhook component and the provider's Mailer bridge — reaches
`MailerEventConsumer` when `bounces.remote_event_consumer` names the provider's webhook route:

```yaml
framework:
    webhook:
        routing:
            mail_provider:
                service: mailer.webhook.request_parser.<provider>
                secret: '%env(MAIL_PROVIDER_WEBHOOK_SECRET)%'
```

| Event | Effect |
|---|---|
| `bounce`, `dropped` | Hard bounce |
| `deferred` | Soft bounce |
| `spam` | Complaint |
| `unsubscribe` | A request to leave |

The delivery is found by the metadata the bundle set on each message, else the subscriber by the address.
