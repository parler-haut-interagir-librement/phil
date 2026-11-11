# Installation

## Requirements

| Requirement | Constraint | Why |
|---|---|---|
| PHP | `>=8.5` | The floor of every PHIL package. |
| `phil/backup` | `^0.1@dev` | The clients, the cipher, the media archive, the rotation and the guard. |
| `doctrine/dbal` | `^4.5` | The connection whose platform chooses the client and whose parameters give the credentials. |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | The configuration tree and the bundle class. |
| `symfony/console` | `^8.2` | The three commands. |
| `symfony/process` | `^8.2` | Running the client tools. |

The client tools of the database — `sqlite3`; `mysqldump` and `mysql`; `mariadb-dump` and `mariadb`; `pg_dump` and
`pg_restore`, of the server's major version or later — must be installed where the commands run; see
phil/backup's installation (`doc/installation.md` of that package).

## Installing

```bash
composer require phil/backup-bundle:^0.1@dev
```

Register the bundle where Flex does not:

```php
// config/bundles.php
return [
    // ...
    Phil\BackupBundle\PhilBackupBundle::class => ['all' => true],
];
```

With DoctrineBundle, the default connection `database_connection` is backed up to `var/backup`, without media, and
kept forever. A key is required: without one, the container build is refused unless `allow_unencrypted: true` says
the backups may be written in clear, for a development machine whose data is not real.

## A first key

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
bin/console secrets:set PHIL_BACKUP_KEY
```

```yaml
# config/packages/phil_backup.yaml
phil_backup:
    key: '%env(PHIL_BACKUP_KEY)%'
```

Keep a copy of the key outside the server: a backup is unreadable without it. Add `var/backup/` to `.gitignore`.
