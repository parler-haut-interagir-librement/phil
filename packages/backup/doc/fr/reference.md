<!-- phil-dev source-sha256=3d58a8d8306687ac64d281117929b053b3ec17d72d25e3a1d78c7c344d86b4e3 -->
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
| `Encryption\BackupCipher` | `__construct($key, $tightenOpenDirectory = true)` ; `encrypt($source, $target)`, `decrypt($source, $target)`, chacun par un fichier temporaire lisible par son seul propriétaire ; `EXTENSION` | Implémenté |
| `Media\MediaArchive` | `isEmpty()`, `write()`, `verify()`, `extract()`, `prune()` | Implémenté |
| `Media\TarWriter` | `create()`, `addDirectory()`, `addFile()`, `close()`, `discard()` | Implémenté |
| `Media\TarReader` | `read($visitor)` | Implémenté |
| `Media\TarEntry` | `$name`, `$directory`, `$mode`, `$size`, `$modified` | Implémenté |
| `Storage\BackupDirectory` | `path()`, `newStem()`, `file()`, `temporary()`, `publish()`, `sets()`, `labelled()`, `latest()`, `set()`, `remove()` ; `MEDIA`, `COPIES` | Implémenté |
| `Storage\BackupSet` | `$stem`, `$time`, `$label`, `$database`, `$media`, `$copy` ; `isLabelled()`, `files()`, `isEncrypted($file)`, `compare()` | Implémenté |
| `Rotation\RotationPolicy` | `$last`, `$daily`, `$weekly`, `$monthly` ; `expired($sets)` | Implémenté |
| `Restore\RestoreGuard` | `checkEnvironment()`, `check()` ; `VARIABLE` | Implémenté |
| `Exception\BackupError` | Interface de chaque exception de la bibliothèque | Implémenté |
| `Exception\BackupFailed` | `RuntimeException` ; `missing()`, `failed()`, `because()` | Implémenté |
| `Exception\InvalidBackupValue` | `InvalidArgumentException` ; `because()` | Implémenté |
| `Exception\RestoreRefused` | `RuntimeException` ; `inProduction()`, `unconfirmed()`, `untyped()`, `mistyped()` | Implémenté |
