# phil/backup-bundle

**Back a Symfony application's database and media files up from its configuration, restore them behind a guard, and test that they restore.**

phil/backup-bundle puts `phil/backup` in a Symfony application. It reads the Doctrine DBAL connection to choose the
client — SQLite, MySQL, MariaDB or PostgreSQL — and to give it the credentials, which never reach a command line. It
backs the media directories up beside the database, encrypts every file with a key read from the environment, and
rotates the backups after each one. Three commands do the work: `phil:backup:create`, `phil:backup:restore` and
`phil:backup:verify`.

A restore is a decision taken twice. In production it is refused unless `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` holds
the name of the database; everywhere it needs `--confirm` and the database's name typed again, asked for when the
console is interactive. The current content is then backed up, labelled `before-restore`, and the restore starts only
once that backup is written; the latest three of these are kept. Media files added since the backup are left, unless
`--prune` removes them. A key is required unless `allow_unencrypted: true` is set. `phil:backup:verify` restores a backup into a copy — a temporary file for SQLite, a
scratch database for a server — and checks it there, without touching the database.

It is for the developers and operators of a Symfony application who need backups they can trust and restores they
cannot run by accident. It does not schedule the backups, copy them off the machine, or watch that they run: the
application's scheduler, storage and monitoring do; see [commands.md](doc/commands.md).

It is a Symfony bundle on `phil/backup`, the Console component, Doctrine DBAL and Symfony Process, and needs the
database's client tools where the commands run.

## Status

| Area | State |
|---|---|
| `PhilBackupBundle`: connection, directory, binaries, timeout, key, allow_unencrypted, media, rotation, restore, verify | Implemented and tested |
| `DatabaseClients`: the client of a DBAL connection, without connecting | Implemented and tested |
| `ProcessCommandRunner`: Symfony Process, no shell, an optional time limit | Implemented and tested |
| `phil:backup:create`, `phil:backup:restore`, `phil:backup:verify` | Implemented and tested on SQLite with the real `sqlite3`; MySQL, MariaDB and PostgreSQL by their command lines |

## Documentation

- [Installation](doc/installation.md) — requirements, installing with Composer, registering the bundle, the client
  tools, a first key.
- [Configuration](doc/configuration.md) — every key of `phil_backup`, its default and effect, the services it
  wires, and each build-time error.
- [Commands](doc/commands.md) — creating, restoring and testing a backup, their options and messages, scheduling,
  and copying a production database to work on.
- [Restore protection](doc/restore-protection.md) — production, the confirmation, the name typed again, and the
  backup taken first.
- [Extension points](doc/extension-points.md) — the services you can rely on, another runner or client.
- [Testing](doc/testing.md) — how the bundle is tested, and how to test your configuration.
- [API reference](doc/reference.md) — every type of the bundle, with its role.

## Quick start

```bash
composer require phil/backup-bundle:^0.1@dev
```

```yaml
# config/packages/phil_backup.yaml
phil_backup:
    key: '%env(PHIL_BACKUP_KEY)%'
    media: { uploads: '%kernel.project_dir%/public/uploads' }
    rotation: { last: 3, daily: 7, weekly: 4, monthly: 12 }
```

```bash
bin/console phil:backup:create
bin/console phil:backup:verify
bin/console phil:backup:restore --confirm            # asks for the database's name
```

## Requirements

PHP 8.5 or later, `phil/backup`, `doctrine/dbal` 4.5, and `symfony/config`, `symfony/console`,
`symfony/dependency-injection` and `symfony/process` 8.2.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. See
[LICENSE](LICENSE).
