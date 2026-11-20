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
`digest.subject.weekly`. An application that sends otherwise implements `DigestSender`.

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
