# Extension points

## Services you can rely on

| Id | Notes |
|---|---|
| `Phil\Backup\Backups` | Public: create, rotate, restore and test sets from the application's own code. Whether a restore may happen is yours to check, with `RestoreGuard`. |
| `phil_backup.database_client` | Private `DatabaseClient` of `connection` (`PhilBackupBundle::DATABASE_CLIENT`); replace or decorate it from a compiler pass to back up another database or by another means. |
| `Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner` | Private; replace it to run the tools elsewhere, in a container of the database's version for example. |
| `Phil\Backup\Restore\RestoreGuard` | Private; reference it to guard a restore of your own. |
| `phil:backup:create`, `phil:backup:restore`, `phil:backup:verify` | The commands. |

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

A labelled set is kept apart from rotation. Messages that start a backup from a queue carry identifiers only: the set
is found again by its file name.
