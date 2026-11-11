<!-- phil-dev source-sha256=fb6cbb705e635b70dcaa82e8d3196024e372a6f647a53a7dd2e3bb4fa0e7026e -->
# Clients de base de données

Un `DatabaseClient` sauvegarde et restaure une base de données avec les outils propres à sa plateforme. La bibliothèque
en a un par plateforme ; chacun reçoit un `CommandRunner` qui trouve et exécute les outils, et ne construit jamais de
commande shell.

## Lignes de commande

| Client | Sauvegarde | Restauration | Fichier |
|---|---|---|---|
| `SqliteClient($path, $runner)` | `sqlite3 <path> ".backup '<file>'"` | `sqlite3 <path> ".restore '<file>'"` | `.sqlite` |
| `MysqlClient($database, $connection, $runner, false)` | `mysqldump --defaults-file=<options> --single-transaction --routines --triggers --result-file=<file> <database>` | `mysql --defaults-file=<options> --system-command=OFF <database> < <file>` | `.sql` |
| `MysqlClient($database, $connection, $runner, true)` | `mariadb-dump`, ou `mysqldump` s'il est absent, avec les mêmes options | `mariadb`, ou `mysql`, avec `--sandbox` | `.sql` |
| `PostgresqlClient($database, $connection, $runner)` | `pg_dump --format=custom --file=<file> --dbname=<database>` | `pg_restore --clean --if-exists --no-owner --single-transaction --dbname=<database> <file>` | `.dump` |

`.backup` écrit une copie cohérente d'un fichier SQLite même pendant que l'application y écrit. Le vidage MySQL est une
seule transaction, avec routines et déclencheurs, et ne nomme aucune base de données : il se restaure donc dans la base
que reçoit le client. Le format personnalisé de PostgreSQL est compressé. Sa restauration supprime chaque objet avant de
le recréer, laisse la propriété au rôle qui se connecte et s'exécute en une seule transaction : une sauvegarde se
restaure en entier ou pas du tout.

## Identifiants de connexion

`$connection` contient `host`, `port`, `user`, `password` et, pour MySQL et MariaDB, `unix_socket`, sous forme de
chaînes ; une clé absente est laissée à la valeur par défaut de l'outil.

**MySQL et MariaDB** les lisent dans un fichier d'options créé par `tempnam()` dans le répertoire temporaire du système
— lisible par son seul propriétaire —, donné avec `--defaults-file` et supprimé une fois l'outil exécuté, même en cas
d'échec. Ce fichier est le seul que l'outil lit : un `~/.my.cnf` laissé sur le serveur n'ajoute aucune option. **PostgreSQL** les lit dans `PGHOST`, `PGPORT`, `PGUSER` et `PGPASSWORD`, donnés au seul processus de
l'outil. Ni l'un ni l'autre ne met un mot de passe sur une ligne de commande, où `ps` le montre à chaque utilisateur de
la machine. SQLite n'en a pas.

## Commandes shell dans un vidage MySQL

Les clients `mysql` et `mariadb` exécutent sur la machine les lignes `\!` et `system` de leur entrée, même en mode
batch : un vidage déposé dans le stockage des sauvegardes, ou remis pour être testé, exécuterait n'importe quelle
commande avec les droits de l'application. Une restauration demande d'abord son `--help` au client et désactive ces
commandes : `--sandbox` lorsque le client l'affiche (MariaDB 10.5.25, 10.6.18, 10.11.8, 11.0.6, 11.1.5, 11.2.4, 11.4.2
et suivantes), sinon `--system-command=OFF` (MySQL 8.0.40, 8.4.3, 9.1.0 et suivantes). Un client qui n'offre ni l'un
ni l'autre est refusé avant de lire le vidage, avec
`… cannot turn its shell commands off, which a dump could run on this machine: install MySQL 8.0.40 or later, or
MariaDB 10.5.25 or later.`

## Noms de fichiers SQLite

`sqlite3` lit le fichier de `.backup` et de `.restore` entre apostrophes. Un nom qui contient une apostrophe ou un
caractère de contrôle, qui terminerait l'argument ou la commande, est refusé avec `BackupFailed`. Une restauration à
partir d'un fichier qui n'existe pas est refusée avant l'exécution de `sqlite3` : `.restore` créerait le fichier vide et
viderait la base de données du même coup.

## Une autre base du même serveur

`withDatabase($name)` donne le même client pour une autre base de données du même serveur, avec les mêmes identifiants —
ou un autre fichier, pour SQLite. Un test de restauration restaure à cet endroit ; voir
[restore-tests.md](restore-tests.md).

## Vérifications

`check()` s'exécute après une restauration dans une copie. `SqliteClient` exécute `PRAGMA integrity_check` et échoue si
la réponse n'est pas `ok`. Les clients MySQL et PostgreSQL ne vérifient rien de plus : la réussite de l'outil de
restauration, qui lit tout le vidage, tient lieu de vérification.

## Identité

`identity()` donne une `DatabaseIdentity` : la plateforme (`sqlite`, `mysql`, `mariadb`, `postgresql`), l'hôte ou null,
et le nom — le fichier, pour SQLite. `describe()` la rédige pour les messages,
`postgresql database shop on db.internal`. `typedName()` est le nom qu'une personne saisit de nouveau pour confirmer une
restauration, le nom du fichier sans son répertoire pour SQLite.

## Échecs

Un outil absent est nommé, avec la marche à suivre :
`pg_dump is not installed or not on the PATH: install your database's client tools, or give the path of the tool.` Un
outil qui échoue est signalé avec sa sortie d'erreur, `pg_dump failed: role "x" does not exist`. Les deux sont des
`BackupFailed`.
