<!-- phil-dev source-sha256=4339b84ac230723a4779851d11a1c2c2d2a210f20ff96e2fbcb3d64a1c832647 -->
# Tests de restauration

`Backups::verify($set, $scratch = null)` prouve ce qui peut être prouvé d'un jeu sans toucher à la base de données, et
retourne une `Verification`.

## Ce que fait un test

1. Chaque fichier chiffré est déchiffré dans un fichier temporaire, ce qui l'authentifie en entier.
2. La base de données est restaurée dans une copie et vérifiée à cet endroit :
   - **SQLite** : dans un fichier temporaire du répertoire de sauvegarde, puis `PRAGMA integrity_check` ; la copie est
     supprimée ;
   - **MySQL, MariaDB, PostgreSQL** : dans la base de travail `$scratch`, sur le même serveur et avec les mêmes
     identifiants ; la réussite de l'outil de restauration tient lieu de vérification. Sans base de travail, la base de
     données n'est pas restaurée, et `restoredInto` est null.
3. L'archive des médias est lue en entier, chaque en-tête et chaque chemin vérifiés ; `mediaFiles` est son nombre de
   fichiers, null si le jeu n'en a pas.

Un échec à n'importe quelle étape est un `BackupFailed` qui nomme le fichier et la cause.

## La base de travail

La base de travail est une base que l'application abandonne aux tests : chaque test remplace son contenu. Créez-la une
fois, vide, avec les droits dont ont besoin les objets de la sauvegarde — pour PostgreSQL, appartenant au rôle sous
lequel le client se connecte. Une base de travail du même nom que la base de données elle-même est refusée :
`The restore test would replace the postgresql database shop itself: give it a scratch database of its own.`

## À quelle fréquence

Un test de restauration du jeu le plus récent après chaque sauvegarde coûte une restauration. Planifiez-le après la
sauvegarde. Une alerte sur son échec signale qu'il existe une sauvegarde qui ne se serait pas restaurée — le jour où on
le découvre plutôt que le jour où on en a besoin.
