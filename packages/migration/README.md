# phil/migration

**Release notes and news that Doctrine migrations send to chat once a run is over.**

phil/migration lets a Doctrine migration announce what it brings. Each migration leaves release notes and news, in
its locales, as it runs. Once the run is over, the notes and news of the migrations that ran to the end are sent
together on chat channels: one announcement per run, however many migrations it holds. Nothing is sent on a dry run,
and messages are cut to fit the longest one each transport takes.

It is for a team that deploys with Doctrine Migrations and wants its users or its own channels told what a release
changed. The notes live in the migration that brings the change, so they ship and run with it. Each channel has a
locale and reads either the notes or the news. In a Symfony application, `phil/migration-bundle` wires all of this
from configuration.

It is a plain PHP library on Doctrine Migrations, `symfony/notifier` and `psr/log`. Two decorators wrap the migration
factory and the migrator of Doctrine Migrations' `DependencyFactory`, so the library sees every run without listening
to any event. Sending goes through the `Publisher` port, with `ChatterPublisher` on Symfony's chatter as its adapter.

## Status

| Part | State |
|---|---|
| `AnnouncingMigration`, `Announcing`, `Announces`, `Announcements` | Implemented and tested |
| `AnnouncingMigrationFactory`, `AnnouncingMigrator` | Implemented and tested |
| `Channel`, `Content`, messages cut to a length, numbered | Implemented and tested |
| `Announcer`, `Publisher`, `ChatterPublisher` | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Reference](doc/reference.md) | The types, what a run announces and when, how messages are cut. |
| [Integration](doc/integration.md) | Wiring the decorators into Doctrine Migrations without Symfony. |
| [Extension points](doc/extension-points.md) | The ports, arguments and data an application chooses or replaces. |
| [Testing](doc/testing.md) | How the library is tested. |

## Quick start

```php
final class Version20261104120000 extends AnnouncingMigration
{
    public function up(Schema $schema): void
    {
        $this->note(['fr' => '* Nouveau calendrier.', 'en' => '* A new calendar.']);
        $this->news(['fr' => new News('Le calendrier', ['Tous les événements, au même endroit.'], 'https://example.com/calendrier')]);
        // ...
    }
}
```

```php
$channels = [
    new Channel('discord_releases', 'fr', title: '# A new release is online{counter}', continued: '## Continued{counter}'),
    new Channel('discord_news', 'fr', Content::News, heading: '# {title}', link: 'Read more: {url}'),
];
$announcer = new Announcer($channels, new ChatterPublisher($chatter), $logger);
```

A run of two migrations sends one message on `discord_releases`:

```
# A new release is online

* Nouveau calendrier.
* Mise à jour des librairies.
```

In a Symfony application, `phil/migration-bundle` wires everything from the configuration.

## Requirements

PHP 8.5 or later with mbstring, `doctrine/migrations` 3.9 or later, `psr/log` and `symfony/notifier`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See [LICENSE](LICENSE).
