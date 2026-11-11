<!-- phil-dev source-sha256=a1aa22f0cbadbb8ce118c7ac04dc2ef0172c41935da6b885b4bacbc088229dd4 -->
# phil/backup

**Sauvegardez une base de données et ses fichiers médias, chiffrés et en rotation, restaurez-les derrière une garde, et
prouvez qu'ils se restaurent.**

phil/backup copie une base de données dans un fichier avec les outils clients propres à sa plateforme — `sqlite3`,
`mysqldump` ou `mariadb-dump`, `pg_dump` — et la restaure avec `sqlite3`, `mysql` ou `mariadb`, `pg_restore`. Les
identifiants de connexion n'atteignent jamais une ligne de commande, où chaque utilisateur de la machine peut les lire :
MySQL et MariaDB les lisent dans un fichier d'options lisible par son seul propriétaire, PostgreSQL dans l'environnement
de l'outil. Les répertoires de médias de l'application — téléversements, images générées, documents — accompagnent la
base de données dans une archive tar, chacun sous un nom plutôt que sous un chemin : une sauvegarde se restaure ainsi
sur une machine dont les répertoires sont ailleurs.

Chaque fichier d'une sauvegarde peut être chiffré avec une clé de 32 octets par le flux secret de libsodium, bloc par
bloc : une sauvegarde de toute taille passe par une quantité fixe de mémoire, et un fichier modifié, tronqué ou échangé
est refusé. Les sauvegardes sont nommées d'après la base de données et l'heure UTC, et une rotation grand-père-père-fils
garde les plus récentes ainsi que la plus récente de chaque jour, de chaque semaine et de chaque mois. Une garde de
restauration refuse une restauration en production à moins qu'une variable d'environnement ne nomme la base de données,
et demande une confirmation et le nom de la base saisi de nouveau. Un test de restauration restaure une sauvegarde dans
une copie — un fichier temporaire pour SQLite, une base de travail pour un serveur — et la vérifie à cet endroit, sans
toucher à la base de données.

Elle existe parce qu'une sauvegarde que personne n'a restaurée est un espoir, pas une sauvegarde, et parce qu'une
sauvegarde contient en clair les données de chaque personne : elle est réservée à son propriétaire, chiffrée là où elle
est conservée et remplacée selon un calendrier. Elle s'adresse à toute application PHP qui conserve ses données dans
SQLite, MySQL, MariaDB ou PostgreSQL. `phil/backup-bundle` l'intègre à Symfony, avec sa configuration et ses commandes.

C'est une simple bibliothèque PHP dont le seul prérequis est `ext-sodium`. Elle ne lance pas elle-même
de processus : un `CommandRunner` fourni par l'application trouve et exécute les outils. Elle ne planifie pas les
sauvegardes, ne les copie pas hors de la machine et ne décide pas du moment où une restauration est sage : le
planificateur, le stockage et les personnes de l'application s'en chargent.

## État

| Partie | État |
|---|---|
| `SqliteClient`, `MysqlClient`, `PostgresqlClient` au-dessus des outils clients, identifiants hors de la ligne de commande | Implémenté et testé : SQLite avec le vrai `sqlite3`, les autres par leurs lignes de commande |
| `BackupCipher` et `BackupKey` : le flux secret de libsodium par blocs, un format versionné | Implémenté et testé |
| `TarWriter`, `TarReader`, `MediaArchive` : archives ustar de répertoires nommés, sans liens | Implémenté et testé |
| `BackupDirectory`, `BackupSet` : jeux nommés d'après la base de données, l'heure UTC et une étiquette | Implémenté et testé |
| `RotationPolicy` : les sauvegardes les plus récentes et la plus récente de chaque jour, semaine ISO et mois | Implémenté et testé |
| `RestoreGuard` : production, confirmation, nom saisi de nouveau | Implémenté et testé |
| `Backups` : créer, faire tourner, trouver, restaurer et tester un jeu | Implémenté et testé |

## Documentation

- [Installation](doc/fr/installation.md) — prérequis, installation, outils clients de chaque plateforme.
- [Clients de base de données](doc/fr/database-clients.md) — la ligne de commande de chaque plateforme, les
  identifiants, la restauration dans une autre base, les vérifications et l'exécuteur de commandes.
- [Jeux de sauvegarde](doc/fr/backup-sets.md) — `Backups`, le répertoire, le nom des fichiers, les étiquettes, trouver
  et supprimer un jeu.
- [Chiffrement](doc/fr/encryption.md) — les clés, le format de fichier, ce qui est authentifié et ce que signifie une
  clé perdue.
- [Fichiers médias](doc/fr/media-files.md) — les répertoires nommés, ce qui est archivé et ce qui est laissé, la
  réécriture des fichiers.
- [Rotation](doc/fr/rotation.md) — les règles, le décompte des périodes et les jeux étiquetés.
- [Restauration](doc/fr/restoring.md) — la garde de restauration, l'ordre d'une restauration et la sauvegarde à faire
  d'abord.
- [Tests de restauration](doc/fr/restore-tests.md) — ce qu'un test prouve pour chaque plateforme, et la base de travail.
- [Points d'extension](doc/fr/extension-points.md) — les ports `DatabaseClient` et `CommandRunner` et leurs contrats.
- [Tests](doc/fr/testing.md) — les tests de la bibliothèque et le seuil de mutation, et comment tester du code qui
  sauvegarde.
- [Référence de l'API](doc/fr/reference.md) — chaque type de la bibliothèque, avec son rôle.

## Démarrage rapide

```sh
composer require phil/backup
```

```php
use Phil\Backup\Backups;
use Phil\Backup\Database\PostgresqlClient;
use Phil\Backup\Encryption\BackupCipher;
use Phil\Backup\Encryption\BackupKey;
use Phil\Backup\Media\MediaArchive;
use Phil\Backup\Rotation\RotationPolicy;
use Phil\Backup\Storage\BackupDirectory;

$backups = new Backups(
    new PostgresqlClient('shop', ['host' => 'db.internal', 'user' => 'shop', 'password' => $password], $runner),
    new BackupDirectory('/var/backups/shop'),
    new BackupCipher(BackupKey::fromBase64($key)),
    new MediaArchive(['uploads' => '/srv/shop/public/uploads']),
    new RotationPolicy(last: 3, daily: 7, weekly: 4, monthly: 12),
);

$set = $backups->create(new DateTimeImmutable());   // shop-20261107-031500.dump.enc and .media.tar.enc
$backups->prune();                                  // the sets the rotation has expired
$backups->verify($set, 'shop_restore_test');        // restored into the scratch database and checked
```

## Prérequis

PHP 8.5 ou plus récent et `ext-sodium` ; les outils clients de la base de données sur la machine qui exécute les
sauvegardes.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
