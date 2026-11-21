# Digest and retention

## The digests

A recipient who chose a daily or weekly digest gets, instead of an email for each notification of a type that allows
it, one email holding their unread notifications the last digest did not hold, changed within the day or the week,
oldest first, up to `digest.limit`. A notification collapsed into after its digest comes back in the next one. A digest
holds only what the centre keeps: a type without the `site` channel is emailed at once.

`Digests::sweep()` finds the recipients with such notifications a page at a time, and sends each who chose that
frequency their digest — through one `SendDigest` message each when a bus carries them. `MailerDigestSender` writes it
with `@PhilNotifications/email/digest.html.twig`, renders it with Twig's body renderer and sends it at once through the
transports of Symfony Mailer — never through `MailerInterface`, which would put a `SendEmailMessage` holding the address
and every line of the digest on the bus when one is routed; a refusal of the transport goes up, for the worker to try
the `SendDigest` message again. The template's context is `frequency` and `notifications`, each with its
`id`, `type`, `title`, `body`, `url` and `count`; its subject is the translation of `digest.subject.daily` or
`digest.subject.weekly`. The text part is `@PhilNotifications/email/digest.txt.twig`, which writes the address of each
notification after its title. An application that sends otherwise implements `DigestSender`.

## The links of a digest

The footer of a digest links to the application's page of notification preferences when `digest.preferences_route`
names its route, and to a signed link that stops the digest when a `DigestUnsubscriber` records it — the `dbal`
preferences, or the service of `digest.unsubscriber`. The templates read them as `preferences_url` and
`unsubscribe_url`, each null when there is none, and the text part writes both addresses in full.

The signed link is also the one-click unsubscription of RFC 8058: each digest carries `List-Unsubscribe: <link>` and
`List-Unsubscribe-Post: List-Unsubscribe=One-Click`, which the large mail providers ask of a recurring email. The link
is signed by Symfony's `uri_signer`, with the application's secret, names the recipient by their identifier, and
expires after a year; each digest carries a new one.

- A `POST` to it — what a mail client sends from its own unsubscribe button, or the button of the page — sets the
  recipient's digest to `never` at once, without a CSRF token or a confirmation, and keeps the rest of their settings.
  The recipient then gets an email for each notification of a type that allows one, as before they chose a digest.
- A `GET` changes nothing, since a link scanner or a preview may follow it: it shows
  `@PhilNotifications/digest/unsubscribe.html.twig`, with a button that posts to the same link.
- A link not signed, altered or expired gets a `403`.

The route comes from `digest_routes.php`, imported outside the login firewall
([Installation](installation.md#the-routes)). The links are written in a worker, where the router knows no request:
`framework.router.default_uri` gives their scheme and host. Without the route, a digest leaves without its signed link
and an error is logged. `digest.unsubscribe: false` turns the link off. With the `config` preferences and a digest
other than `never` by default, the container refuses to build unless `digest.unsubscriber` names a service or the link
is turned off: nobody could stop a digest sent to everyone.

## Open and click tracking

Some mail providers count the opens of an email with an invisible image and follow its clicks through redirected
links. Each digest carries Symfony Mailer's `TrackingHeader` (`X-Track`), which the providers' transports map to their
own settings; a message's own header wins over the application-wide default of `framework.mailer.tracking` (the
`tracking` option of MailerBundle, `mailer.tracking`). The digest turns both off, whatever that default.

An application that asked its members' consent implements `EmailTrackingConsent` and names it in
`digest.tracking_consent`: each digest then follows the opens and the clicks the recipient agreed to.

```php
final readonly class MemberTracking implements EmailTrackingConsent
{
    public function __construct(private Members $members) {}

    public function opens(string $recipient): bool
    {
        return $this->members->consents($recipient, 'email.opens');
    }

    public function clicks(string $recipient): bool
    {
        return $this->members->consents($recipient, 'email.clicks');
    }
}
```

A transport that knows no such setting — plain SMTP, among others — sends the header as it is, without effect.

## The schedule

With Symfony Scheduler, the schedule `phil_notifications` dispatches `SendDigests` every day at `digest.at` and every
week on `digest.weekday` at that time, and `PurgeNotifications` every day at `retention.purge_at`, in
`schedule.timezone`. A worker runs it:

```sh
bin/console messenger:consume scheduler_phil_notifications
```

## The retention

`Retention::purge()` deletes the read and archived notifications last changed more than `retention.read_days` ago, and
the unread ones more than `retention.unread_days` ago, a thousand at a time, up to a hundred thousand a run.
`bin/console phil:notifications:purge` runs it at once.
