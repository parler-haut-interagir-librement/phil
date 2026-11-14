# API reference

Every type under `Phil\BackupBundle\`. "Implemented" means implemented and covered by unit tests.

| Type | Role | State |
|---|---|---|
| `PhilBackupBundle` | `AbstractBundle`, alias `phil_backup`; the configuration tree and the services; `DATABASE_CLIENT` | Implemented |
| `Infrastructure\Doctrine\DatabaseClients` | `forConnection($connection, $runner)`: the client of a DBAL connection | Implemented |
| `Infrastructure\Process\ProcessCommandRunner` | The `CommandRunner` over Symfony Process; the warning after `warn_after`, the stop after `timeout` | Implemented |
| `Infrastructure\Process\WatchedProcess` | Internal: the Symfony `Process` that lets the runner watch a tool at each of its timeout checks | Implemented |
| `Application\Event\BackupToolRunningLong` | Event: a client tool still running after `warn_after`; `tool`, `pid`, `seconds` | Implemented |
| `Infrastructure\Console\CreateBackupCommand` | `phil:backup:create` | Implemented |
| `Infrastructure\Console\RestoreBackupCommand` | `phil:backup:restore` | Implemented |
| `Infrastructure\Console\VerifyBackupCommand` | `phil:backup:verify` | Implemented |
