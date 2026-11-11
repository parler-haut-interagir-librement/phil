<!-- phil-dev source-sha256=08deb459934754a5082bbd8fca62e9ec70937f5395fe1dab1090c799b8a14500 -->
# Protection des restaurations

`phil:backup:restore` remplace chaque ligne de la base de données. Avant de changer quoi que ce soit, la commande passe
trois vérifications, dans cet ordre, puis sauvegarde le contenu actuel.

## 1. Production

Dans l'un des `restore.production_environments` (`[prod]`), une restauration est refusée à moins que la variable
d'environnement `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` ne contienne **le nom de la base de données restaurée** :

```bash
PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE=shop bin/console phil:backup:restore var/backup/shop-20261107-031500.dump.enc --confirm --database=shop
```

La variable se lit dans l'environnement du processus, ou là où le Dotenv de Symfony l'écrit. Elle n'est jamais lue dans
la configuration, pour qu'un déploiement ne puisse pas la laisser activée, et une valeur restée définie après la
restauration d'une autre base, ou définie à `1`, ne suffit pas. Définissez-la pour la seule exécution qui en a besoin,
comme ci-dessus, et nulle part ailleurs.

## 2. Confirmation

Sans `--confirm`, la commande dit ce qu'elle remplacerait et par quoi, puis s'arrête.

## 3. Le nom saisi de nouveau

Le nom de la base de données — le nom du fichier sans son répertoire pour SQLite, `app.sqlite` — est saisi de nouveau,
avec `--database` ou à l'invite d'une console interactive :

```
 The postgresql database shop on db.internal will be replaced with var/backup/shop-20261107-031500.dump.enc. Type its name, shop, to go on:
 > shop
```

Un nom différent, même par la casse seulement, est refusé. Une console non interactive qui ne donne pas `--database` est
refusée.

## La sauvegarde faite d'abord

Une fois les trois vérifications passées, le contenu actuel — la base de données, et les médias sauf si `--no-media` est
donné — est sauvegardé dans un jeu étiqueté `before-restore`, chiffré comme tous les autres et jamais mis en rotation.
La restauration ne commence qu'une fois ce jeu écrit ; une restauration du mauvais jeu se défait en restaurant
celui-ci :

```bash
bin/console phil:backup:restore var/backup/shop-20261107-104512-before-restore.dump.enc --confirm --database=shop
```

Une fois une restauration réussie, les plus anciens jeux étiquetés `before-restore` sont supprimés, pour qu'il reste les
trois plus récents — `restore.keep_before_restore` ; une restauration ratée n'en supprime aucun. Deux restaurations dans
la même seconde gardent les deux jeux, le second étant nommé avec un numéro de copie :
`shop-20261107-104512-before-restore_2.dump.enc`.
