<!-- phil-dev source-sha256=5e373e7e7d50111572ef60e6b3af6b11e3c61cf99323f7310f229ae53eb06101 -->
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

## Planification

Le bundle ne planifie rien. Avec Symfony Scheduler, ou avec cron :

```
15 3 * * *  cd /srv/shop && bin/console phil:backup:create --quiet && bin/console phil:backup:verify --quiet
```

Copiez le répertoire hors de la machine après chaque sauvegarde — les jeux sont chiffrés, la clé reste sur place — et
déclenchez une alerte sur un code de sortie non nul.

## Une copie de la production pour travailler

```bash
bin/console phil:backup:create                                                 # in production
# copy the set to the other machine, then there:
bin/console phil:backup:restore /tmp/shop-20261107-031500.dump.enc --confirm --database=shop_dev
```

Le contenu local est sauvegardé d'abord, comme partout. Une copie qui contient des données de personnes est anonymisée
avant d'être partagée ; `phil/privacy-bundle` s'en charge, et sa documentation donne la séquence complète.
