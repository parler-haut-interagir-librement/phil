<!-- phil-dev source-sha256=9222f1483e699d4090714000f72f8eb23a5db758505223a977652879aa115cce -->
# Référence de l'API

Chaque type sous `Phil\BackupBundle\`. « Implémenté » signifie implémenté et couvert par des tests unitaires.

| Type | Rôle | État |
|---|---|---|
| `PhilBackupBundle` | `AbstractBundle`, alias `phil_backup` ; l'arbre de configuration et les services ; `DATABASE_CLIENT`, `LOCK_FACTORY` | Implémenté |
| `Infrastructure\Doctrine\DatabaseClients` | `forConnection($connection, $runner)` : le client d'une connexion DBAL | Implémenté |
| `Infrastructure\Process\ProcessCommandRunner` | Le `CommandRunner` au-dessus de Symfony Process ; l'avertissement après `warn_after`, l'arrêt après `timeout` | Implémenté |
| `Infrastructure\Process\WatchedProcess` | Interne : le `Process` de Symfony qui laisse l'exécuteur surveiller un outil à chacune de ses vérifications du délai | Implémenté |
| `Application\Event\BackupToolRunningLong` | Événement : un outil client toujours en cours après `warn_after` ; `tool`, `pid`, `seconds` | Implémenté |
| `Infrastructure\Console\CreateBackupCommand` | `phil:backup:create` | Implémenté |
| `Infrastructure\Console\RestoreBackupCommand` | `phil:backup:restore` | Implémenté |
| `Infrastructure\Console\VerifyBackupCommand` | `phil:backup:verify` | Implémenté |
| `Infrastructure\Console\MonitorBackupCommand` | `phil:backup:monitor` ; `DEFAULT_MAX_AGE_HOURS`, 26 | Implémenté |
| `Infrastructure\Lock\BackupLock` | Le verrou exclusif d'un répertoire de sauvegarde au-dessus de Symfony Lock : `exclusively($work)`, `resource()` ; `PREFIX` | Implémenté |
