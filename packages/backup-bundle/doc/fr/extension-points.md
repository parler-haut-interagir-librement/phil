<!-- phil-dev source-sha256=7c7cae4e5a7bfeeeb4b729134728142626fb36e920ead4bf26beece415716fb0 -->
# Points d'extension

## Les services sur lesquels compter

| Identifiant | Remarques |
|---|---|
| `Phil\Backup\Backups` | Public : créer, faire tourner, restaurer et tester des jeux depuis le code de l'application. C'est à vous de vérifier si une restauration peut avoir lieu, avec `RestoreGuard`. |
| `phil_backup.database_client` | `DatabaseClient` privé de `connection` (`PhilBackupBundle::DATABASE_CLIENT`) ; remplacez-le ou décorez-le depuis une passe de compilation pour sauvegarder une autre base de données ou par un autre moyen. |
| `Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner` | Privé ; remplacez-le pour exécuter les outils ailleurs, par exemple dans un conteneur de la version de la base de données. |
| `Phil\Backup\Restore\RestoreGuard` | Privé ; faites-y référence pour protéger une restauration qui vous est propre. |
| `phil:backup:create`, `phil:backup:restore`, `phil:backup:verify` | Les commandes. |

## Un autre exécuteur ou client

Votre propre exécuteur implémente `Phil\Backup\Database\CommandRunner`, et votre client
`Phil\Backup\Database\DatabaseClient` ; leurs contrats se trouvent dans les points d'extension de phil/backup
(`doc/extension-points.md` de ce paquet). Enregistrez les vôtres sous les identifiants ci-dessus :

```php
final class DockerRunnerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->setDefinition(ProcessCommandRunner::class, new Definition(DockerCommandRunner::class, ['postgres:17']));
    }
}
```

## Sauvegarder depuis l'application

```php
$set = $backups->create(new DateTimeImmutable(), 'before-import');
// ... a risky import ...
```

Un jeu étiqueté est tenu à l'écart de la rotation. Les messages qui lancent une sauvegarde depuis une file d'attente ne
portent que des identifiants : le jeu se retrouve par son nom de fichier.
