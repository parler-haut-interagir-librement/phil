# Extension points

## Services you can rely on

| Id | Notes |
|---|---|
| `Phil\Backup\Backups` | Public: create, rotate, restore and test sets from the application's own code. Whether a restore may happen is yours to check, with `RestoreGuard`. |
| `phil_backup.database_client` | Private `DatabaseClient` of `connection` (`PhilBackupBundle::DATABASE_CLIENT`); replace or decorate it from a compiler pass to back up another database or by another means. |
| `Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner` | Private; replace it to run the tools elsewhere, in a container of the database's version for example. |
| `Phil\Backup\Restore\RestoreGuard` | Private; reference it to guard a restore of your own. |
| `Phil\BackupBundle\Infrastructure\Lock\BackupLock` | Private, with the lock enabled; reference it to run work of your own on the sets — a copy off the machine, a removal by age — under the lock the commands hold: `$lock->exclusively(fn () => ...)`. |
| `lock.factory`, or any `LockFactory` | Name it in `lock.factory` to keep the lock in a store several machines share. |
| `phil:backup:create`, `phil:backup:restore`, `phil:backup:verify`, `phil:backup:monitor` | The commands. |

## Another runner or client

A runner of your own implements `Phil\Backup\Database\CommandRunner`, a client `Phil\Backup\Database\DatabaseClient`;
their contracts are in phil/backup's extension points (`doc/extension-points.md` of that package). Register yours under the
ids above:

```php
final class DockerRunnerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->setDefinition(ProcessCommandRunner::class, new Definition(DockerCommandRunner::class, ['postgres:17']));
    }
}
```

## Backing up from the application

```php
$set = $backups->create(new DateTimeImmutable(), 'before-import');
// ... a risky import ...
```

A labelled set is kept apart from rotation. `Backups` itself takes no lock: work of the application's own that may
run beside the commands holds `BackupLock`. Messages that start a backup from a queue carry identifiers only: the set
is found again by its file name.
