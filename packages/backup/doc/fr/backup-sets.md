<!-- phil-dev source-sha256=4a541664230c8b2867b37c782793d025007830d6f275fe07eb967f85336eedd7 -->
# Jeux de sauvegarde

## `Backups`

`Backups` assemble la bibliothèque :

```php
new Backups(
    DatabaseClient $client,
    BackupDirectory $directory,
    ?BackupCipher $cipher = null,                  // null: files in clear
    MediaArchive $media = new MediaArchive([]),    // no media directories
    ?RotationPolicy $rotation = null,              // null: every set is kept
    bool $allowUnencrypted = false,                // true: with a key, sets in clear are still read
);
```

| Méthode | Effet |
|---|---|
| `create($at, $label = '', $media = true)` | Sauvegarde la base de données, et les répertoires de médias sauf si `$media` est faux, dans un nouveau jeu ; retourne le `BackupSet` |
| `prune()` | Supprime les jeux que la rotation a fait expirer, et les retourne |
| `find($file)` | Le jeu du fichier de base de données donné, où qu'il soit, ou, avec null, le jeu sans étiquette le plus récent |
| `keepLatest($label, $keep)` | Supprime les jeux de l'étiquette, sauf les `$keep` plus récents, et les retourne ; voir [restoring.md](restoring.md) |
| `restore($set, $media = true, $prune = false)` | Remplace le contenu de la base de données par le jeu et écrit ses fichiers médias, en élaguant les autres avec `$prune` ; retourne une `Restoration` |
| `verify($set, $scratch = null)` | Le test de restauration ; voir [restore-tests.md](restore-tests.md) |

Un jeu qui échoue à mi-chemin — la base de données écrite, l'archive des médias non — est supprimé en entier : aucun
fichier d'une sauvegarde ratée ne reste pour être pris pour un bon, ni aucun fichier temporaire.

## Le répertoire

`BackupDirectory($path, $tightenOpenDirectory = true)` est créé à la première utilisation, lisible par son seul
propriétaire (`0700`). **Une sauvegarde contient les données de chaque personne** : gardez le répertoire hors du
contrôle de version et hors des racines Web, et copiez les jeux hors de la machine avec des outils qui les gardent
privés.

Un répertoire qui existe déjà et qui est ouvert à son groupe ou à d'autres utilisateurs — un mode tel que `0755` — est
restreint à `0700` avant que quoi que ce soit y soit écrit. Quand il ne peut pas l'être, parce qu'un autre utilisateur
en est le propriétaire, ou quand `tightenOpenDirectory` est faux, la sauvegarde échoue avec un `BackupFailed` qui nomme
le répertoire et son mode, et rien n'est écrit. Chercher, lister et lire des jeux ne change jamais le mode du
répertoire.

Chaque fichier est écrit sous forme de fichier temporaire du répertoire, `.phil-backup-` suivi de caractères
aléatoires, créé en `0600` par `tempnam()` : aucun autre utilisateur ne peut lire un seul octet de ce fichier, à aucun
moment. Le fichier temporaire d'un fichier du jeu reçoit ensuite son nom en une seule étape, par un lien physique, si
bien que le fichier apparaît entier et ne remplace jamais un fichier existant : deux sauvegardes qui choisissent le même
nom au même moment échouent au lieu de s'écraser, et celle qui échoue ne supprime que ses propres fichiers. Sur un
système de fichiers sans liens physiques, le fichier temporaire est plutôt renommé. Une copie en clair n'existe que
pendant qu'un fichier est chiffré, déchiffré ou testé, sous forme d'un autre fichier temporaire du répertoire. Chaque
fichier temporaire est supprimé quoi qu'il arrive.

Un répertoire atteint par un lien symbolique est utilisé là où le lien pointe : ses fichiers temporaires y sont créés.

## Noms

Un jeu est nommé d'après la base de données, l'heure UTC et une étiquette facultative :

```
shop-20261107-031500.dump.enc             the database
shop-20261107-031500.media.tar.enc        its media archive
shop-20261107-041000-before-restore.dump  a labelled set, in clear
```

La partie de la base de données est son nom, ou le nom du fichier SQLite sans son répertoire ni son extension, où chaque
suite de caractères autres que des lettres, des chiffres, `-` et `_` est remplacée par `-`. L'heure est celle du `$at`
de `create()`, en UTC : les noms se trient ainsi dans l'ordre chronologique, quel que soit le fuseau horaire du serveur.
Une étiquette est formée de lettres minuscules, de chiffres et de `-`, et commence par une lettre ou un chiffre ; toute
autre étiquette est refusée avec `InvalidBackupValue`.

Un deuxième jeu créé dans la même seconde avec la même étiquette reçoit un numéro de copie après un trait de
soulignement, `_2`, puis `_3` et ainsi de suite jusqu'à `BackupDirectory::COPIES` (100) : aucun jeu n'en remplace un
autre. Le numéro de copie ne fait pas partie de l'étiquette — `shop-20261107-041000-before-restore_2` porte l'étiquette
`before-restore` — et `BackupSet::$copy` le contient, 1 pour le premier jeu. Un radical est pris dès qu'un fichier du
répertoire commence par ce radical suivi d'un point ; le 101e jeu d'un même radical est refusé avec `BackupFailed`.

## Trouver des jeux

`sets($extension)` lit les jeux du répertoire à partir du nom de leurs fichiers de base de données, chiffrés ou non, du
plus ancien au plus récent — par heure, puis par numéro de copie, puis par radical, dans l'ordre de
`BackupSet::compare()`. Un fichier dont le nom ne suit pas le motif, ou contient une date impossible, n'est pas un jeu
et n'est jamais touché. `latest($extension)` est le jeu le plus récent **sans étiquette** : un jeu étiqueté — la copie
faite avant une restauration, une copie anonymisée — se restaure en nommant son fichier. `labelled($extension, $label)`
donne les jeux d'une étiquette, du plus ancien au plus récent. Chacune reçoit un dernier argument, `$encrypted` : vrai
ne lit que les jeux chiffrés, faux que ceux en clair, null, la valeur par défaut, les deux.

`set($file, $extension)` lit le jeu de tout fichier donné par son chemin : l'archive des médias placée à côté se trouve
par son radical, et a le chiffrement du fichier de base de données — `.media.tar.enc` à côté d'un fichier `.enc`,
`.media.tar` à côté d'un fichier en clair, jamais l'inverse. Un fichier nommé autrement forme un jeu à lui seul, daté par son heure de modification, sans médias. Un
fichier qui n'existe pas est refusé.

`remove($set)` supprime les fichiers du jeu.
