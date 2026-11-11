<!-- phil-dev source-sha256=9360769a4f11d2a75092069d014f9db820eb8998518b22c816bf748ced65f5f3 -->
# Référence de l'API

Chaque type sous `Phil\Backup\`. « Implémenté » signifie implémenté et couvert par des tests unitaires.

| Type | Rôle | État |
|---|---|---|
| `Backups` | `client()`, `directory()`, `create()`, `prune()`, `keepLatest()`, `find()`, `restore()`, `verify()` | Implémenté |
| `Restoration` | `$mediaFiles`, `$removedFiles` | Implémenté |
| `Verification` | `$restoredInto` (`?DatabaseIdentity`), `$mediaFiles` (`?int`) | Implémenté |
| `Database\DatabaseIdentity` | `$platform`, `$host`, `$name` ; `describe()`, `isFile()`, `typedName()` | Implémenté |
| `Database\DatabaseClient` | `identity()`, `extension()`, `backup()`, `restore()`, `withDatabase()`, `check()` | Port ; trois clients |
| `Database\CommandRunner` | `find($candidates)`, `run($command, $environment, $input)` | Port |
| `Database\SqliteClient`, `MysqlClient`, `PostgresqlClient` | Le client de chaque plateforme | Implémenté |
| `Encryption\BackupKey` | `fromBase64()`, `generate()`, `toBase64()`, `$bytes` | Implémenté |
| `Encryption\BackupCipher` | `encrypt($source, $target)`, `decrypt($source, $target)` ; `EXTENSION` | Implémenté |
| `Media\MediaArchive` | `isEmpty()`, `write()`, `verify()`, `extract()`, `prune()` | Implémenté |
| `Media\TarWriter` | `create()`, `addDirectory()`, `addFile()`, `close()` | Implémenté |
| `Media\TarReader` | `read($visitor)` | Implémenté |
| `Media\TarEntry` | `$name`, `$directory`, `$mode`, `$size`, `$modified` | Implémenté |
| `Storage\BackupDirectory` | `path()`, `newStem()`, `file()`, `temporary()`, `sets()`, `labelled()`, `latest()`, `set()`, `remove()` ; `MEDIA`, `COPIES` | Implémenté |
| `Storage\BackupSet` | `$stem`, `$time`, `$label`, `$database`, `$media`, `$copy` ; `isLabelled()`, `files()`, `isEncrypted($file)`, `compare()` | Implémenté |
| `Rotation\RotationPolicy` | `$last`, `$daily`, `$weekly`, `$monthly` ; `expired($sets)` | Implémenté |
| `Restore\RestoreGuard` | `checkEnvironment()`, `check()` ; `VARIABLE` | Implémenté |
| `Exception\BackupError` | Interface de chaque exception de la bibliothèque | Implémenté |
| `Exception\BackupFailed` | `RuntimeException` ; `missing()`, `failed()`, `because()` | Implémenté |
| `Exception\InvalidBackupValue` | `InvalidArgumentException` ; `because()` | Implémenté |
| `Exception\RestoreRefused` | `RuntimeException` ; `inProduction()`, `unconfirmed()`, `untyped()`, `mistyped()` | Implémenté |
