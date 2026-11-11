# Extension points

The library declares no service: an application wires its two decorators into Doctrine Migrations — see
[Integration](integration.md). What it implements is where messages go, and which migrations announce.

## `Publisher`

`Phil\Migration\Publisher` — sends one message of an announcement. `ChatterPublisher` sends it through the chatter of
`symfony/notifier`.

| Method | Contract |
|---|---|
| `publish(string $transport, string $message): void` | Sends the message, already cut to the channel's length, on the named transport; throws when it cannot: the announcer logs it and leaves the rest of that channel, without failing the run |

A publisher of the application's own sends anywhere else: a webhook, a mailing list, a log.

## `Announcing`

`Phil\Migration\Announcing` — a migration that announces. `AnnouncingMigrationFactory` gives it the run's
announcements.

| Method | Contract |
|---|---|
| `announceWith(Announcements $announcements): void` | Keeps the announcements, for `note()` and `news()` to fill |

`AnnouncingMigration` is the shipped base class; a base class of the application's own extends `AbstractMigration`,
implements `Announcing` and uses the `Announces` trait.

## Wiring

| Point | How |
|---|---|
| The application's own `MigrationFactory` or `Migrator` | Wrapped by `AnnouncingMigrationFactory` and `AnnouncingMigrator` instead of the dependency factory's |
| Where and how a release is told | The `Channel`s given to `Announcer`: transport, locale, notes or news, titles and the longest message |
