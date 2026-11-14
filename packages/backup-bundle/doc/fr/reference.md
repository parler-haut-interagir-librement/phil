<!-- phil-dev source-sha256=0dd41f0c858dd27a90b70cce67f2274096caf1cc8a88bea239a77907f6294fb7 -->
# Référence de l'API

Chaque type sous `Phil\BackupBundle\`. « Implémenté » signifie implémenté et couvert par des tests unitaires.

| Type | Rôle | État |
|---|---|---|
| `PhilBackupBundle` | `AbstractBundle`, alias `phil_backup` ; l'arbre de configuration et les services ; `DATABASE_CLIENT` | Implémenté |
| `Infrastructure\Doctrine\DatabaseClients` | `forConnection($connection, $runner)` : le client d'une connexion DBAL | Implémenté |
| `Infrastructure\Process\ProcessCommandRunner` | Le `CommandRunner` au-dessus de Symfony Process ; l'avertissement après `warn_after`, l'arrêt après `timeout` | Implémenté |
| `Infrastructure\Process\WatchedProcess` | Interne : le `Process` de Symfony qui laisse l'exécuteur surveiller un outil à chacune de ses vérifications du délai | Implémenté |
| `Application\Event\BackupToolRunningLong` | Événement : un outil client toujours en cours après `warn_after` ; `tool`, `pid`, `seconds` | Implémenté |
| `Infrastructure\Console\CreateBackupCommand` | `phil:backup:create` | Implémenté |
| `Infrastructure\Console\RestoreBackupCommand` | `phil:backup:restore` | Implémenté |
| `Infrastructure\Console\VerifyBackupCommand` | `phil:backup:verify` | Implémenté |
