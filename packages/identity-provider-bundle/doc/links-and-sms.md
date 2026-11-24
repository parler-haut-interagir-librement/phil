# Sign-in links and phone numbers

## Sign-in links

The module `login_link` offers `/login/link`: the address typed, a link from the `login_link` authenticator of Symfony
Security sent to it through the transports of the Mailer component, and the same answer whether the address has an account or not. The
firewall configures the authenticator, its check route `phil_identity_provider_login_link_check`, its lifetime, its
uses and the properties it signs over — `userIdentifier` and `passwordChangedAt` keep a link from outliving a
password change:

```yaml
login_link:
    check_route: phil_identity_provider_login_link_check
    signature_properties: [userIdentifier, passwordChangedAt]
    lifetime: 600
    max_uses: 1
    check_post_only: true
```

The link is consumed by POST only. Following it shows a page with one button, "Sign in", whose form submits the
parameters of the link to the same route, without script; the authenticator checks them there and signs the person
in. A mail scanner or a link preview that follows the link only reads that page, and the single use stays for the
person. The bundle sets `check_post_only: true` on every `login_link` authenticator that checks
`phil_identity_provider_login_link_check`, whatever the firewall says (`LoginLinkPostOnlyPass`). The page carries
over the parameters of the link only — names of letters, digits, `_`, `.` and `-`, at most 16 —, sends no referrer
(`Referrer-Policy: no-referrer`), and is kept out of caches and search engines.

The link is generated on `public_origin`, never on the host the request named: see
[Where the links point](accounts.md#where-the-links-point).

A sign-in link states no method: it counts for `IS_AUTHENTICATED_RECENTLY`, not for `PHIL_STEP_UP` of an account
that has a stronger method, and an account with an authenticator application is asked its code next.

## Phone numbers

The module `sms` verifies a phone number: `/account/phone` takes a number in E.164 — spaces, dashes, dots and
parentheses are dropped —, sends six digits through the port `SmsSender`, and verifies the number when the code comes
back. The code is kept hashed in the session with the number, an expiry of `sms.lifetime` seconds and `sms.attempts`
tries. The default `SmsSender` is a texter of the Notifier component on its SMS transports, without a bus, to whichever
SMS transport the application configured; `sms.sender` names a service of its own instead, which keeps the code out
of any queue too. The rate of sending belongs to the application's rate
limiter in front of the route.

A verified number lets the application reach the person, and appears in the claims of the scope `phone`. Text messages
as a second factor are planned, not implemented: SMS can be intercepted, and the authenticator application or a
passkey is the second factor the bundle offers.

## Why account emails and SMS never go through Messenger

A verification link, a reset link and a sign-in link each take the account for whoever holds them, for as long as they
last — up to an hour for a reset link —; a code sent by text message proves the number. The bundle hands them to a
transport in the request that asks for them, and no message of Messenger ever carries them:

- `MailerAccountMailer` renders each email — its HTML part and its text part — with the body renderer of Twig, then
  hands it to the transports of the Mailer component (`mailer.transports`). It never calls `MailerInterface`, which
  puts a `SendEmailMessage` on the bus when Messenger routes it: the email would wait unrendered, its context and the
  link in it, in the transport, in the failure transport after a failed try, and in their backups. Reading any of them
  would be enough to take the accounts.
- The default `SmsSender` is a texter on the SMS transports of the Notifier component (`texter.transports`), without a
  bus. The texter `texter` puts an `SmsMessage` on the bus when Messenger routes it, with the number and the code.

The recipe of Messenger routes both to the `async` transport:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        routing:
            Symfony\Component\Mailer\Messenger\SendEmailMessage: async
            Symfony\Component\Notifier\Message\SmsMessage: async
```

That routing stays right for the other emails of the application, and changes nothing for the bundle's. A service
of the application that replaces `AccountMailer` or `SmsSender` keeps the same rule: it sends at once, or it puts on
the bus a message that carries identifiers only — the account and the kind of link —, and its handler builds the link
in the worker.

Each email writes its link in full in its text part, `@PhilIdentityProvider/email/<message>.txt.twig`, so that a reader
that shows no HTML still sees it; and carries a `TrackingHeader` that turns off the opens and the clicks the provider
of the transport follows, so that the provider does not rewrite the link to count its clicks.
