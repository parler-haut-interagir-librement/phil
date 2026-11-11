# API reference

Every type under `Phil\Backup\`. "Implemented" means implemented and covered by unit tests.

| Type | Role | State |
|---|---|---|
| `Backups` | `client()`, `directory()`, `create()`, `prune()`, `keepLatest()`, `find()`, `restore()`, `verify()` | Implemented |
| `Restoration` | `$mediaFiles`, `$removedFiles` | Implemented |
| `Verification` | `$restoredInto` (`?DatabaseIdentity`), `$mediaFiles` (`?int`) | Implemented |
| `Database\DatabaseIdentity` | `$platform`, `$host`, `$name`; `describe()`, `isFile()`, `typedName()` | Implemented |
| `Database\DatabaseClient` | `identity()`, `extension()`, `backup()`, `restore()`, `withDatabase()`, `check()` | Port; three clients |
| `Database\CommandRunner` | `find($candidates)`, `run($command, $environment, $input)` | Port |
| `Database\SqliteClient`, `MysqlClient`, `PostgresqlClient` | The client of each platform | Implemented |
| `Encryption\BackupKey` | `fromBase64()`, `generate()`, `toBase64()`, `$bytes` | Implemented |
| `Encryption\BackupCipher` | `encrypt($source, $target)`, `decrypt($source, $target)`; `EXTENSION` | Implemented |
| `Media\MediaArchive` | `isEmpty()`, `write()`, `verify()`, `extract()`, `prune()` | Implemented |
| `Media\TarWriter` | `create()`, `addDirectory()`, `addFile()`, `close()` | Implemented |
| `Media\TarReader` | `read($visitor)` | Implemented |
| `Media\TarEntry` | `$name`, `$directory`, `$mode`, `$size`, `$modified` | Implemented |
| `Storage\BackupDirectory` | `path()`, `newStem()`, `file()`, `temporary()`, `sets()`, `labelled()`, `latest()`, `set()`, `remove()`; `MEDIA`, `COPIES` | Implemented |
| `Storage\BackupSet` | `$stem`, `$time`, `$label`, `$database`, `$media`, `$copy`; `isLabelled()`, `files()`, `isEncrypted($file)`, `compare()` | Implemented |
| `Rotation\RotationPolicy` | `$last`, `$daily`, `$weekly`, `$monthly`; `expired($sets)` | Implemented |
| `Restore\RestoreGuard` | `checkEnvironment()`, `check()`; `VARIABLE` | Implemented |
| `Exception\BackupError` | Interface of every exception of the library | Implemented |
| `Exception\BackupFailed` | `RuntimeException`; `missing()`, `failed()`, `because()` | Implemented |
| `Exception\InvalidBackupValue` | `InvalidArgumentException`; `because()` | Implemented |
| `Exception\RestoreRefused` | `RuntimeException`; `inProduction()`, `unconfirmed()`, `untyped()`, `mistyped()` | Implemented |
