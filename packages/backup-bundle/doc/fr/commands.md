<!-- phil-dev source-sha256=6627eb3335deea4d9faf7c186b27fc0ba3e76dcb2cd522923fab15b93fa32755 -->
# Commandes

## `phil:backup:create`

```bash
bin/console phil:backup:create
bin/console phil:backup:create --label=before-migration --no-media
```

| Option | Effet |
|---|---|
| `--label=LABEL` | Nomme le jeu `<database>-<time>-<label>` et le tient à l'écart de la rotation ; lettres minuscules, chiffres et `-` |
| `--no-media` | Sauvegarde la base de données seule |
| `--no-rotate` | Ne supprime aucun jeu expiré cette fois-ci |

Elle écrit un nouveau jeu dans le répertoire — le fichier de base de données et, s'il y a des répertoires de médias,
l'archive des médias, chacun chiffré lorsqu'une clé est configurée —, puis supprime les jeux que la rotation a fait
expirer :

```
The postgresql database shop on db.internal is backed up in /srv/shop/var/backup/shop-20261107-031500.dump.enc.
The media files are archived in /srv/shop/var/backup/shop-20261107-031500.media.tar.enc.
The expired backup shop-20261031-031500 is removed.
```

Un échec — un outil absent, une base de données qui refuse la connexion, un répertoire où l'on ne peut pas écrire — est
affiché, et la commande se termine avec `1` ; aucun fichier du jeu raté ne reste.

## `phil:backup:restore`

```bash
bin/console phil:backup:restore --confirm
bin/console phil:backup:restore var/backup/shop-20261107-031500.dump.enc --confirm --database=shop
```

| Argument ou option | Effet |
|---|---|
| `file` | Le fichier de base de données du jeu ; par défaut, le jeu sans étiquette le plus récent du répertoire |
| `--confirm` | Restaure ; sans cette option, la commande dit seulement ce qu'elle remplacerait et par quoi |
| `--database=NAME` | Le nom de la base de données saisi de nouveau ; demandé lorsque la console est interactive |
| `--no-media` | Restaure la base de données seule |
| `--prune` | Supprime les fichiers médias que le jeu ne contient pas, une fois ses fichiers écrits ; désactivé par défaut |

La [protection des restaurations](restore-protection.md) s'exécute d'abord ; chaque refus se termine avec `2` et ne
change rien. Ensuite :

```
The current content is backed up in /srv/shop/var/backup/shop-20261107-104512-before-restore.dump.enc first.
The postgresql database shop on db.internal is restored from var/backup/shop-20261107-031500.dump.enc.
1284 media file(s) are restored from var/backup/shop-20261107-031500.media.tar.enc.
```

Avec `--prune`, les répertoires de médias sont ensuite laissés tels qu'ils ont été sauvegardés — les fichiers téléversés
depuis sont supprimés, et se retrouvent dans le jeu fait d'abord :

```
12 media file(s) the backup does not hold are removed.
```

Une fois la restauration réussie, les jeux étiquetés `before-restore` sont supprimés, sauf les plus récents, au nombre
de `restore.keep_before_restore`, trois par défaut. Chacun est nommé :

```
The older backup shop-20261101-091500-before-restore is removed.
```

Un échec après la garde se termine avec `1`. Un échec de la sauvegarde faite d'abord arrête la commande avant la
restauration.

## `phil:backup:verify`

```bash
bin/console phil:backup:verify
bin/console phil:backup:verify var/backup/shop-20261107-031500.dump.enc
```

Elle teste un jeu — le fichier donné, ou le jeu sans étiquette le plus récent — sans toucher à la base de données :

```
The files of shop-20261107-031500 decrypt and authenticate.
The database of shop-20261107-031500 restores: it was restored into the postgresql database shop_restore_test on db.internal and checked.
The media archive of shop-20261107-031500 reads whole: 1284 file(s).
```

Une sauvegarde SQLite est restaurée dans un fichier temporaire et passe `PRAGMA integrity_check`. La sauvegarde d'un
serveur est restaurée dans `verify.database` ; sans cette base, la commande le signale et teste le reste. Un échec se
termine avec `1`. Voir les tests de restauration de phil/backup (`doc/restore-tests.md` de ce paquet).

## `phil:backup:monitor`

```bash
bin/console phil:backup:monitor
bin/console phil:backup:monitor --max-age=50
```

| Option | Effet |
|---|---|
| `--max-age=HOURS` | Le nombre d'heures au plus que peut avoir la dernière sauvegarde, un nombre entier, 1 ou plus ; par défaut `monitor.max_age_hours`, 26 |

Elle dit si le jeu le plus récent sans étiquette — celui que prendrait `phil:backup:restore` — a été fait il y a moins
d'heures que cela, d'après l'heure de son nom :

```
The most recent backup, shop-20261107-031500, was taken 7 h 42 min ago: less than 26 hour(s).
```

Elle se termine avec `0` quand la sauvegarde est récente, `1` quand elle est plus ancienne ou qu'il n'y en a aucune, et
`2` pour un âge maximal qui n'est pas un nombre entier d'heures. Avec une clé, un jeu en clair n'est jamais compté. Elle
ne fait que lister le répertoire : elle ne prend aucun verrou et n'ouvre aucun jeu, de sorte qu'elle peut s'exécuter
aussi souvent que la surveillance le veut, même pendant une sauvegarde. Savoir si le jeu se restaure est la question de
`phil:backup:verify`.

## Le verrou

`phil:backup:create`, `phil:backup:restore` et `phil:backup:verify` tiennent un verrou exclusif par répertoire de
sauvegarde, par Symfony Lock, du début de leur travail à sa fin : la sauvegarde et la rotation qui la suit ; la copie
faite avant une restauration, la restauration et la suppression des copies plus anciennes ; le test de restauration.
Une rotation ne supprime donc jamais un jeu qu'une autre commande écrit ou teste, et deux planifications qui se
chevauchent ne s'exécutent jamais ensemble. Aucune n'attend le verrou : une commande qui le trouve pris échoue
aussitôt, ne change rien et se termine avec `1` :

```
Another backup, rotation, restore or restore test of /srv/shop/var/backup is running: try again once it ends.
```

La restauration prend le verrou une fois que la [protection des restaurations](restore-protection.md) l'a laissée
passer, de sorte qu'une question qui attend le nom de la base de données ne bloque jamais une sauvegarde. Par défaut,
le verrou vit dans un magasin flock du répertoire temporaire du système, qui sert les commandes d'une seule machine et
est libéré à la fin d'un processus, quelle que soit sa fin. Plusieurs machines qui écrivent dans le même répertoire
partagent un magasin par `lock.factory` ; voir [Configuration](configuration.md).

## Planification

Le bundle ne planifie rien. Avec Symfony Scheduler, ou avec cron :

```
15 3 * * *  cd /srv/shop && bin/console phil:backup:create --quiet && bin/console phil:backup:verify --quiet
*/30 * * * *  cd /srv/shop && bin/console phil:backup:monitor --quiet
```

Copiez le répertoire hors de la machine après chaque sauvegarde — les jeux sont chiffrés, la clé reste sur place — et
déclenchez une alerte sur un code de sortie non nul, de la sauvegarde et de `phil:backup:monitor`, qui attrape aussi une
sauvegarde qui n'a jamais démarré.

## Une copie de la production pour travailler

```bash
bin/console phil:backup:create                                                 # in production
# copy the set to the other machine, then there:
bin/console phil:backup:restore /tmp/shop-20261107-031500.dump.enc --confirm --database=shop_dev
```

Le contenu local est sauvegardé d'abord, comme partout. Une copie qui contient des données de personnes est anonymisée
avant d'être partagée ; `phil/privacy-bundle` s'en charge, et sa documentation donne la séquence complète.

### Combien de temps garder une copie étiquetée `anonymized`

Une copie anonymisée ainsi, puis sauvegardée avec `--label=anonymized`, **contient encore des renseignements
personnels** : ses clés primaires, les colonnes qu'aucune règle ne réécrit et son texte libre la relient à la
production. Selon la loi québécoise sur la protection des renseignements personnels dans le secteur privé, elle est au
mieux dépersonnalisée, non anonymisée, et d'autres lois la voient de la même façon. Elle demande une période de
conservation qui lui est propre, comme tout renseignement personnel, et sa fuite est un incident de confidentialité.

La rotation ne supprime jamais un jeu étiqueté : une telle copie reste jusqu'à ce qu'on la supprime. Fixez sa période de
deux façons :

- **par nombre**, après chaque nouvelle copie : `Backups::keepLatest('anonymized', 1)` supprime toutes les copies sauf
  la dernière ;
- **par âge**, selon un calendrier : supprimez les copies plus anciennes que la période, comme le montre le guide de
  rotation de phil/backup (`doc/rotation.md` de ce paquet, *Labelled sets and their retention*), ou avec une règle de
  conservation de `phil/privacy-bundle` qui l'appelle.

Notez la période à côté des autres périodes de conservation de l'application, et supprimez la base de données de
travail restaurée une fois le travail terminé : c'est la même information, hors du répertoire de sauvegarde.
