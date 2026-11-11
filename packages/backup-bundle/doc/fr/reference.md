<!-- phil-dev source-sha256=e376fcb312cbb3bbe54145c89ff77334ccc7cf363bbe066e06522e6481f952e6 -->
# Référence de l'API

Chaque type sous `Phil\BackupBundle\`. « Implémenté » signifie implémenté et couvert par des tests unitaires.

| Type | Rôle | État |
|---|---|---|
| `PhilBackupBundle` | `AbstractBundle`, alias `phil_backup` ; l'arbre de configuration et les services ; `DATABASE_CLIENT` | Implémenté |
| `Infrastructure\Doctrine\DatabaseClients` | `forConnection($connection, $runner)` : le client d'une connexion DBAL | Implémenté |
| `Infrastructure\Process\ProcessCommandRunner` | Le `CommandRunner` au-dessus de Symfony Process | Implémenté |
| `Infrastructure\Console\CreateBackupCommand` | `phil:backup:create` | Implémenté |
| `Infrastructure\Console\RestoreBackupCommand` | `phil:backup:restore` | Implémenté |
| `Infrastructure\Console\VerifyBackupCommand` | `phil:backup:verify` | Implémenté |
