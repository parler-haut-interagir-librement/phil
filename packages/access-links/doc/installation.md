# Installation

```sh
composer require phil/access-links
```

The package needs PHP 8.5 or later with `ext-mbstring` and `ext-sodium`, `phil/url` and `psr/clock`. The reminders as
messages need `symfony/messenger` 8.2 or later; the database store needs `doctrine/dbal` 4.5 or later:

```sh
composer require symfony/messenger doctrine/dbal
```

## The key

The link tokens are stored as their HMAC-SHA-256 under a key of at least 32 bytes. Generate it once and keep it in the
secrets vault, never in the repository:

```sh
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
php bin/console secrets:set ACCESS_LINKS_KEY
```

A key lost is every link sent lost. See [Links](links.md) for its rotation.

## The services in Symfony

The library declares no service. In `config/services.yaml`:

```yaml
services:
    Phil\AccessLinks\Dbal\DbalInvitationStore:
        arguments: ['@doctrine.dbal.default_connection']

    Phil\AccessLinks\TokenHasher:
        arguments: ['%env(base64:ACCESS_LINKS_KEY)%']

    app.access_links.page:
        class: Phil\Url\Url
        factory: ['Phil\Url\Url', 'fromString']
        arguments: ['https://events.example/rsvp']

    Phil\AccessLinks\LinkFormat:
        arguments: ['@app.access_links.page']

    Phil\AccessLinks\ReminderSchedule:
        arguments: [['P3D', 'P7D']]

    Phil\AccessLinks\AccessLinks:
        arguments:
            $store: '@Phil\AccessLinks\Dbal\DbalInvitationStore'

    Phil\AccessLinks\Messenger\ReminderDispatcher: ~
    Phil\AccessLinks\Messenger\SendInvitationReminderHandler: ~
```

The clock is Symfony's `clock` service, autowired by its `Psr\Clock\ClockInterface`. The handler carries
`#[AsMessageHandler]`; it needs the application's `ReminderSender`, aliased to
`Phil\AccessLinks\Contract\ReminderSender`. Route `Phil\AccessLinks\Messenger\SendInvitationReminder` to an
asynchronous transport, and run the dispatcher from a scheduled task; see [Reminders](reminders.md).

Then create the tables, from a migration that calls `DbalInvitationStore::configureSchema()`; see
[Storage](storage.md).
