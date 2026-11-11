# phil/migration-bundle

**Each run of `doctrine:migrations:migrate` announced once on your chat channels.**

phil/migration-bundle brings `phil/migration` into a Symfony application. The migrations of
DoctrineMigrationsBundle announce what they bring, and each run of `doctrine:migrations:migrate` is announced once,
however many migrations ran. The announcement goes to the chat channels of the configuration through
`symfony/notifier`.

It is for a Symfony application that deploys with DoctrineMigrationsBundle and wants a release told on Discord or
another chat transport the notifier supports. Each channel names its transport, its locale, whether it carries the
release notes or the news, its titles and the longest message it takes. Outside Symfony, `phil/migration` plugs into
Doctrine Migrations by hand.

A migration announces by extending `AnnouncingMigration`, or a base class of the application's own that uses the
`Announces` trait. The bundle decorates the migration factory and the migrator that DoctrineMigrationsBundle gives,
and sends through the chatter transports of `framework.notifier`.

## Status

| Part | State |
|---|---|
| Channels from the configuration, sent through the chatter | Implemented and tested |
| The migration factory and the migrator wrapped, the application's own factory decorated | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, registering, the chatter transports. |
| [Configuration](doc/configuration.md) | The channels and their options. |
| [Migrations](doc/migrations.md) | Writing a migration that announces, a base class of one's own, a factory of one's own. |
| [Extension points](doc/extension-points.md) | What an application implements, configures, replaces or relies on. |
| [Testing](doc/testing.md) | How the bundle is tested. |
| [Reference](doc/reference.md) | Every type and service of the bundle. |

## Quick start

```yaml
# config/packages/phil_migration.yaml
phil_migration:
    channels:
        discord_releases:
            locale: fr
            title: '# Une nouvelle version est en ligne{counter}'
            continued: '## Suite{counter}'
        discord_news:
            locale: fr
            content: news
            heading: '# {title}'
            link: 'Pour voir la nouvelle : {url}'
```

```php
final class Version20261104120000 extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Nouveau calendrier.', 'en' => '* A new calendar.']);
        // ...
    }
}
```

## Requirements

PHP 8.5 or later, Symfony 8.2 or later, DoctrineMigrationsBundle 3.7 or later, `phil/migration`, and a chatter
transport of `symfony/notifier` for each channel.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
