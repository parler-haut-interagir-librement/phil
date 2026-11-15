# Installation

```sh
composer require phil/newsletter-bundle
```

The bundle needs PHP 8.5 or later, Symfony 8.2 or later with FrameworkBundle, TwigBundle, the mailer, Messenger, the
scheduler, the rate limiter, CSRF protection and SecurityBundle, Twig 3.30 or later and a Doctrine DBAL 4.5 connection.

With Symfony Flex, the recipe registers the bundle, writes `config/packages/phil_newsletter.yaml` with the site's base
address and one sender profile, imports the routes under `/newsletter` in `config/routes/phil_newsletter.yaml`, and
adds `NEWSLETTER_SECRET` to `.env`: give it a random value of 32 bytes or more in production. Without Flex, register
the bundle:

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
`doctrine:migrations:diff` writes the migration. See [Storage](storage.md).

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

## The Markdown editor

With AssetMapper, the bundle adds its Stimulus controller under `@phil/newsletter-bundle`; enable it in
`assets/controllers.json`, then include the editor in a form. See [Campaigns](campaigns.md#the-markdown-editor).
