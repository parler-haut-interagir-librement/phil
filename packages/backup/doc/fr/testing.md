<!-- phil-dev source-sha256=83cb92695f67944f061a2897e7e41edc0cdf8056b216b430a0b90a51962d5320 -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste :

| Domaine | Ce qui est testé |
|---|---|
| `Database` | les lignes de commande de MySQL, de MariaDB et de PostgreSQL enregistrées sans serveur (jeux de données d'essai `RecordingRunner`, `FailingRunner`), avec le contenu et le mode du fichier d'options et sa suppression après un échec ; les commandes SQLite, les noms de fichiers refusés, la vérification d'intégrité ; les identités |
| `Encryption` | des tailles autour de la frontière d'un bloc qui font l'aller-retour avec la disposition exacte ; une autre clé, un octet inversé, des fichiers tronqués, échangés ou prolongés refusés ; les clés |
| `Media` | des archives relues telles qu'elles ont été écrites, les longs chemins, les en-têtes endommagés, les liens et périphériques refusés, les fichiers qui changent ou sont trop grands (jeu de données d'essai `LyingFile`), une archive privée pendant qu'elle est écrite et qui reçoit son chemin quand elle est fermée, abandonnée ou jamais fermée ; des répertoires nommés archivés et réécrits ailleurs, les liens, les sockets, les chemins dangereux et les répertoires illisibles ; l'élagage des fichiers, des liens et des répertoires vidés, un répertoire que l'archive ne nomme pas laissé intact |
| `Storage`, `Rotation`, `Restore` | les noms, l'UTC, les étiquettes, les numéros de copie jusqu'à leur borne, l'analyse et le tri ; un répertoire ouvert restreint ou refusé, des fichiers temporaires jamais laissés ailleurs, un répertoire derrière un lien symbolique, un nom jamais publié par-dessus un autre fichier ; chaque règle de la rotation sur des dates connues ; chaque refus de la garde |
| `Backups` | des jeux chiffrés et en clair avec le vrai `sqlite3` (jeu de données d'essai `ProcessRunner`, sur Symfony Process) : création, chaque fichier privé pendant qu'il est écrit (jeu de données d'essai `WatchingClient`), une autre sauvegarde du même nom ni remplacée ni supprimée, nettoyage après un échec, rotation, conservation des derniers jeux d'une étiquette, restauration avec et sans élagage, archive des médias lue avant la base de données, tests de restauration sur SQLite et sur un client MySQL enregistré |

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/backup/tests
```

Les tests SQLite exigent l'outil en ligne de commande `sqlite3` dans le `PATH`. Les vérifications qui supposent un
fichier ou un répertoire illisible ou non inscriptible sont sautées lorsqu'elles s'exécutent en tant que root. La
bibliothèque est tenue à un score de mutation couvert de 100 % par Infection.

## Tester du code qui sauvegarde

Donnez aux clients un `CommandRunner` qui enregistre, pour vérifier les lignes de commande sans serveur, et exécutez le
client SQLite sur un fichier temporaire avec un vrai exécuteur pour tester une sauvegarde et une restauration complètes.
Comparez les heures par leurs horodatages ou leurs chaînes formatées, jamais avec `==`.
