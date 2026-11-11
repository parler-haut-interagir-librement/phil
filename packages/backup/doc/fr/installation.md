<!-- phil-dev source-sha256=40bfad694a73d9f216dbf375e9937d19c91e7ef5af7f13f0cde2ca0e3510ec58 -->
# Installation

## Prérequis

| Prérequis | Pourquoi |
|---|---|
| PHP 8.5 ou plus récent | Classes en lecture seule, constantes typées, `new` sans parenthèses |
| `ext-sodium` | Le flux secret qui chiffre les sauvegardes, et le base64 à temps constant des clés |

Les outils clients de la base de données doivent être installés là où s'exécutent les sauvegardes :

| Plateforme | Sauvegarde | Restauration | Paquet système, par exemple |
|---|---|---|---|
| SQLite | `sqlite3` | `sqlite3` | `sqlite3` |
| MySQL | `mysqldump` | `mysql` | `mysql-client` |
| MariaDB | `mariadb-dump`, ou `mysqldump` | `mariadb`, ou `mysql` | `mariadb-client` |
| PostgreSQL | `pg_dump` | `pg_restore` | `postgresql-client`, de la version majeure du serveur ou d'une version ultérieure |

## Installer

```sh
composer require phil/backup
```

C'est une bibliothèque, pas un bundle : il n'y a rien à enregistrer. `phil/backup-bundle` construit chacun des objets
ci-dessous à partir d'une configuration Symfony. Ailleurs, construisez une seule fois un `Backups` avec son client, son
répertoire, son chiffreur, ses médias et sa rotation, et un `CommandRunner` qui exécute des processus — voir
[extension-points.md](extension-points.md).
