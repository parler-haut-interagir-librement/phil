<!-- phil-dev source-sha256=01622c615337952bc6751abfe739e9bd1c31c2cba47369ce97cf05f3b72a38bf -->
# Installation

## Prérequis

| Prérequis | Contrainte | Pourquoi |
|---|---|---|
| PHP | `>=8.5` | Le plancher de chaque paquet PHIL. |
| `phil/backup` | `^0.1@dev` | Les clients, le chiffreur, l'archive des médias, la rotation et la garde. |
| `doctrine/dbal` | `^4.5` | La connexion dont la plateforme choisit le client et dont les paramètres donnent les identifiants de connexion. |
| `symfony/config`, `symfony/dependency-injection` | `^8.2` | L'arbre de configuration et la classe du bundle. |
| `symfony/console` | `^8.2` | Les trois commandes. |
| `symfony/process` | `^8.2` | L'exécution des outils clients. |

Les outils clients de la base de données — `sqlite3` ; `mysqldump` et `mysql` ; `mariadb-dump` et `mariadb` ; `pg_dump`
et `pg_restore`, de la version majeure du serveur ou d'une version ultérieure — doivent être installés là où s'exécutent
les commandes ; voir l'installation de phil/backup (`doc/installation.md` de ce paquet).

## Installer

```bash
composer require phil/backup-bundle:^0.1@dev
```

Enregistrez le bundle là où Flex ne le fait pas :

```php
// config/bundles.php
return [
    // ...
    Phil\BackupBundle\PhilBackupBundle::class => ['all' => true],
];
```

Avec DoctrineBundle, la connexion par défaut `database_connection` est sauvegardée dans `var/backup`, sans médias, et
conservée indéfiniment. Une clé est exigée : sans elle, la construction du conteneur est refusée, sauf si
`allow_unencrypted: true` indique que les sauvegardes peuvent être écrites en clair, pour une machine de développement
dont les données ne sont pas réelles.

## Une première clé

```bash
php -r 'echo base64_encode(random_bytes(32)), PHP_EOL;'
bin/console secrets:set PHIL_BACKUP_KEY
```

```yaml
# config/packages/phil_backup.yaml
phil_backup:
    key: '%env(PHIL_BACKUP_KEY)%'
```

Gardez une copie de la clé hors du serveur : une sauvegarde est illisible sans elle. Ajoutez `var/backup/` à
`.gitignore`.
