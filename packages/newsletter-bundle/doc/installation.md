# Installation

```sh
composer require phil/newsletter-bundle
```

The bundle needs PHP 8.5 or later, Symfony 8.2 or later with FrameworkBundle, TwigBundle, the mailer, Messenger, the
scheduler, the rate limiter, CSRF protection and SecurityBundle, Twig 3.30 or later and a Doctrine DBAL 4.5 connection.

With Symfony Flex, the recipe registers the bundle, writes `config/packages/phil_newsletter.yaml` with the site's base
address and one sender profile, imports the routes under `/newsletter` in `config/routes/phil_newsletter.yaml`, and
adds `NEWSLETTER_SECRET` to `.env`, empty: give it a random value of 32 bytes or more, out of the repository — a
Symfony secret, `.env.local`, or the environment of the server:

```sh
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
php bin/console secrets:set NEWSLETTER_SECRET
```

The bundle refuses a key shorter than 16 bytes — an empty one among them — and one that begins with `change-me` or
`changeme`, the placeholders examples use: the first service that signs a link, tags a VERP address or hashes a
suppressed address fails with `LinkRefused`. Without Flex, register the bundle:

```php
// config/bundles.php
Phil\NewsletterBundle\PhilNewsletterBundle::class => ['all' => true],
```

then write its configuration (see [Configuration](configuration.md)) and import its routes.

## The routes

```yaml
# config/routes/phil_newsletter.yaml
phil_newsletter:
    resource: '@PhilNewsletterBundle/config/routes.php'
    prefix: /newsletter
```

| Route | Path | What it is |
|---|---|---|
| `phil_newsletter_subscribe` | `/subscribe/{list}` | The subscription form of a list, by its key |
| `phil_newsletter_confirm` | `/confirm/{token}` | The confirmation of a double opt-in |
| `phil_newsletter_unsubscribe` | `/unsubscribe/{token}` | The unsubscription, and the mail client's one-click `POST` |
| `phil_newsletter_preferences` | `/preferences/{token}` | The preference center |
| `phil_newsletter_click` | `/c/{token}` | A tracked link |
| `phil_newsletter_preview` | `/preview` | The preview of the Markdown editor, for the configured role |

## The tables

`NewsletterSchema::configure()` adds the tables to a schema. With the ORM, `NewsletterSchemaListener`, registered on
its `postGenerateSchema` event when DoctrineBundle is there, adds them to the schema it generates, and
`doctrine:migrations:diff` writes the migration, and the one of each later version. See [Storage](storage.md).

## The approvers

A campaign is sent once a user of `ROLE_NEWSLETTER_APPROVER` approved it. Give the role to the people who approve, in
the application's security:

```yaml
# config/packages/security.yaml
security:
    role_hierarchy:
        ROLE_EDITOR_IN_CHIEF: [ROLE_NEWSLETTER_APPROVER]
```

`approval.role` names another role, `approval.four_eyes` asks for an approver other than the author, and
`approval.required: false` sends without approval. See [Campaigns](campaigns.md#approval).

## The workers

Route the newsletter's messages to an asynchronous transport, and consume it with the schedule `newsletter`:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            newsletter: '%env(MESSENGER_TRANSPORT_DSN)%'
        routing:
            'Phil\NewsletterBundle\Application\Message\SendCampaign': newsletter
            'Phil\NewsletterBundle\Application\Message\SendDelivery': newsletter
            'Phil\NewsletterBundle\Application\Message\SendConfirmation': newsletter
            'Phil\NewsletterBundle\Application\Message\FetchBounces': newsletter
            'Phil\NewsletterBundle\Application\Message\DeliverAudienceWebhook': newsletter
```

```sh
php bin/console messenger:consume newsletter scheduler_newsletter
```

A transport whose delays are short — a message waiting for the next opening of a send window may wait days — is
fine: the delivery stays queued, and a later message picks it up.

The handlers of `SendDelivery` and `SendConfirmation` send through Symfony Mailer's transports
(`mailer.transports`), never through `MailerInterface`: they already run in a worker, and with `SendEmailMessage`
routed to a transport, each email would wait in a second queue with the subscriber's address and its signed
confirmation or unsubscription link. Through the transports, those messages carry identifiers only, and a refusal of
the transport fails the handler: Messenger sees it and retries the message, a delivery marked failed in the meantime.
The sender profile's `X-Transport`, the pace of `SendThrottle`, `List-Unsubscribe` and `List-Unsubscribe-Post` are
applied as before, and so is the mailer's configuration — `envelope`, `headers`.

Routing `SendEmailMessage` stays the application's choice for its own emails. An application that does keeps
personal information in its Messenger transport and in its failure transport: it needs a retention rule for the
failure transport — `messenger:failed:show` to look, `messenger:failed:remove` to purge — and a word in its privacy
policy.

## The Markdown editor

With AssetMapper, the bundle adds its Stimulus controller under `@phil/newsletter-bundle`; enable it in
`assets/controllers.json`, then include the editor in a form. See [Campaigns](campaigns.md#the-markdown-editor).
