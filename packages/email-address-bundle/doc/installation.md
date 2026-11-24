# Installation

```sh
composer require phil/email-address-bundle
```

Then register the bundle in `config/bundles.php`, which the Flex recipe does:

```php
Phil\EmailAddressBundle\PhilEmailAddressBundle::class => ['all' => true],
```

The package needs PHP 8.5 or later and Symfony 8.2 or later.

## The parts and their packages

The bundle registers each part only when the package it needs is installed:

| Part | Package |
|---|---|
| The constraints `DeliverableEmailAddress` and `AcceptedEmailAddress`, `EmailAddresses`, the bounce rules | always |
| `EmailAddressType` | `symfony/form` |
| The route of the known domains | `symfony/routing` |
| `phil:email-address:read-bounce` and `phil:email-address:poll-mailbox` | `symfony/console` |
| The consumer of a provider's webhook | `symfony/remote-event`, with `symfony/webhook` and the provider's Mailer bridge |
| `BounceDecidedMessage` sent on to a worker | `symfony/messenger` |

## The route

The Stimulus controller reads the known domains, the top-level slips and the role addresses from a route of the
bundle. Import it, as the recipe does, and give its URL to the configuration:

```yaml
# config/routes/phil_email_address.yaml
phil_email_address:
    resource: '@PhilEmailAddressBundle/config/routes.php'
    prefix: /email-address
```

```yaml
# config/packages/phil_email_address.yaml
phil_email_address:
    suggestions:
        url: /email-address/domains.json
```

The answer is the same for everyone: public, kept a day, and answered `304 Not Modified` when the browser sends back
its ETag.

## The Stimulus controller

The controller ships in `assets/` with a `package.json` that StimulusBundle reads: with AssetMapper, it is enabled in
`assets/controllers.json` under the name `phil--email-address-bundle--email-address`, as for any controller of a
bundle. `EmailAddressType` writes its attributes on the field when `suggestions.url` is set.

The live region the controller writes in has the class `phil-email-address-hint`; style it as the site's other hints.
Without JavaScript, the same suggestion or warning is the field's help text once the form is submitted.

## The bounce inputs

- A provider's webhook: configure the Webhook component for the provider's Mailer bridge, then name the webhook in
  `bounces.remote_event_consumers`.
- A raw message: have the mail server pipe the bounce address to `bin/console phil:email-address:read-bounce`.
- The bounce mailbox: give a Maildir in `bounces.maildir`, or a service implementing `BounceMailbox` in
  `bounces.mailbox`, and run `phil:email-address:poll-mailbox` from the scheduler.

[Bounces](bounces.md) describes each one.
