# Notifications

`Submissions::submit()` keeps the entry first, then tells every `EntryNotifier` in turn. A notifier that fails —
`NotificationFailed` from a transport, or any other error: a template the application overrides, a mailer's
refusal, an application's notifier — is logged with the entry's id, the form's key, the error's class and message —
never the values — and the other notifiers still run. The entry is kept whatever happens, and the visitor's page
says it was sent, so nothing is lost and nothing is sent twice.

## Email

With `sender` set, `EmailNotifier` sends each entry to its form's recipients:

- from the site's own address — a visitor's address in `From` would fail the domain's SPF and DMARC;
- with the first email field in `Reply-To`, so that answering answers the visitor;
- never to the visitor, which would make a form a relay for spam;
- with `X-Auto-Response-Suppress: OOF, AutoReply`;
- in the entry's language, every field asked and its value, from `@PhilForms/email/entry.html.twig` and
  `.txt.twig`; the value of a sensitive field is withheld, and a sensitive email field never answers.

A form without recipients sends no email to them.

### Other notifications

A form sends up to 20 other emails of each entry, each to 1 to 50 addresses, with its own subject, under a
condition on the values:

```php
$form->notifyAlso([
    Notification::to(['sales@example.com'], 'Sales: {name}', ['==' => [['var' => 'department'], 'Sales']]),
    Notification::to(['support@example.com'], 'Support: {name}', ['==' => [['var' => 'department'], 'Support']]),
    Notification::to(['archive@example.com']),
]);
```

The addresses are the form's, never one the visitor typed. A subject is one line of 1 to 255 characters, the form's
title by default; each `{field}` is replaced by the value of a text field on one line, and a subject may name
neither a checkbox nor a sensitive field. A condition reads fields of the form as a field's condition does
([forms](forms.md#questions-asked-under-a-condition)); one that reads a field the entry did not ask does not hold.
Every email is tried; when one fails, the first failure is reported once the others are sent.

### Sending through Messenger

`EmailNotifier` sends through Symfony Mailer's transports (`mailer.transports`), never through `MailerInterface`: the
email is rendered and sent in the request that kept the entry, and a refusal of the transport is known at once and
logged as above. An application that routes `SendEmailMessage` to an asynchronous Messenger transport still queues
nothing of an entry: through `MailerInterface`, the queued message would hold the unrendered email with the entry, its
form and every value in its context — a sealed value in clear with `allow_unsealed_sensitive` —, kept by the transport
and by its failure transport until someone retries or removes it.

What stays the application's: the mailer's own configuration — DSN, `X-Transport`, `envelope`, `headers` —, which the
transports apply as they would to any email, and the emails of its own notifiers. One that queues its emails keeps
personal information in its Messenger transport and its failure transport: it needs a retention rule for the failure
transport — `messenger:failed:show` to look, `messenger:failed:remove` to purge — and a word in its privacy policy.

## Webhook

With `webhook_secret` set, `WebhookNotifier` posts each entry to its form's webhook as a Standard Webhooks message,
signed with `phil/standard-webhooks`, following no redirect:

```json
{
    "type": "form.entry.submitted",
    "timestamp": "2026-11-04T12:00:05.123Z",
    "data": {
        "form": "registration",
        "entry": "01a1a533-…",
        "submitted_at": "2026-11-04T12:00:00Z",
        "locale": "fr",
        "values": {"name": "Ada", "consent": true}
    }
}
```

The values of sensitive fields are left out; their names are then listed under `data.withheld`.

The `webhook-id` is `msg_` and the entry's id, so that a receiver's deduplication absorbs a second delivery. An
answer other than `2xx` fails. A form without a webhook posts nothing.

Entries carry personal data, so a webhook is an `https` URL on a public host: `Form::notify()` refuses `http`,
`localhost` and addresses of private, loopback, link-local or reserved networks, and with `symfony/http-client` the
notifier posts through `NoPrivateNetworkHttpClient`, the service `phil_forms.webhook_client`, which refuses a name
that resolves to such an address when the request is made.

## The application's own

A service implementing `EntryNotifier` is tagged `phil_forms.notifier` by autoconfiguration and told of every entry:
to add it to a CRM, a newsletter, a ticket.
