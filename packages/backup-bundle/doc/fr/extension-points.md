<!-- phil-dev source-sha256=e594201524802cf545e9e4e171a4c9c3b6bbcd1026032e131e569f8e50333d81 -->
# Points d'extension

## Les services sur lesquels compter

| Identifiant | Remarques |
|---|---|
| `Phil\Backup\Backups` | Public : créer, faire tourner, restaurer et tester des jeux depuis le code de l'application. C'est à vous de vérifier si une restauration peut avoir lieu, avec `RestoreGuard`. |
| `phil_backup.database_client` | `DatabaseClient` privé de `connection` (`PhilBackupBundle::DATABASE_CLIENT`) ; remplacez-le ou décorez-le depuis une passe de compilation pour sauvegarder une autre base de données ou par un autre moyen. |
| `Phil\BackupBundle\Infrastructure\Process\ProcessCommandRunner` | Privé ; remplacez-le pour exécuter les outils ailleurs, par exemple dans un conteneur de la version de la base de données. |
| `Phil\Backup\Restore\RestoreGuard` | Privé ; faites-y référence pour protéger une restauration qui vous est propre. |
| `Phil\BackupBundle\Infrastructure\Lock\BackupLock` | Privé, avec le verrou activé ; faites-y référence pour exécuter un travail qui vous est propre sur les jeux — une copie hors de la machine, une suppression par âge — sous le verrou que tiennent les commandes : `$lock->exclusively(fn () => ...)`. |
| `lock.factory`, ou toute `LockFactory` | Nommez-la dans `lock.factory` pour garder le verrou dans un magasin que plusieurs machines partagent. |
| `phil:backup:create`, `phil:backup:restore`, `phil:backup:verify`, `phil:backup:monitor` | Les commandes. |

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

Un jeu étiqueté est tenu à l'écart de la rotation. `Backups` lui-même ne prend aucun verrou : le travail de
l'application qui peut s'exécuter à côté des commandes tient `BackupLock`. Les messages qui lancent une sauvegarde depuis une file d'attente ne
portent que des identifiants : le jeu se retrouve par son nom de fichier.
