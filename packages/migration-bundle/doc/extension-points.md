# Extension points

What an application configures, replaces or relies on. The types are `phil/migration`'s.

## Configuration

| Option | What it extends |
|---|---|
| `channels` | Where and how each release is told: one channel per transport and locale, notes or news, titles, the longest message — see [Configuration](configuration.md) |

## Services

| Service | How an application relies on it |
|---|---|
| `Phil\Migration\Publisher` | Replaced by a service of the application's own to send anywhere else than a chatter transport; `ChatterPublisher` by default, registered when a channel is configured |
| `Doctrine\Migrations\Version\MigrationFactory`, `Doctrine\Migrations\Migrator` | The application's own, given to DoctrineMigrationsBundle, stay the ones that do the work: the bundle decorates them — see [Migrations](migrations.md#a-factory-of-ones-own) |

## Migrations

A migration announces by extending `AnnouncingMigration`, or a base class of the application's own that implements
`Announcing` and uses the `Announces` trait — see [Migrations](migrations.md#a-base-class-of-ones-own).
