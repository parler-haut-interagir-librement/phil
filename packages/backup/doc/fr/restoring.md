<!-- phil-dev source-sha256=e581b2486419b09e5f3bd1b1f65b6ca46ee35ad2e22637669c16fff007d85b43 -->
# Restauration

Une restauration remplace chaque ligne de la base de données. `Backups::restore()` la fait sans rien demander ; c'est la
`RestoreGuard` qui décide d'avance si elle peut avoir lieu.

## La garde de restauration

`new RestoreGuard($environment, $productionEnvironments = ['prod'])` vérifie, dans cet ordre :

1. **La production.** Dans l'un des environnements de production, la variable d'environnement
   `PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE` doit contenir le nom de la base de données restaurée. Une valeur restée
   définie après la restauration d'une autre base, ou définie à `1`, ne suffit pas : l'autorisation porte sur cette
   base, pour cette exécution.
2. **La confirmation.** La restauration est confirmée.
3. **Le nom saisi de nouveau**, tel que le donne `DatabaseIdentity::typedName()` : le nom de la base de données, ou le
   nom du fichier SQLite sans son répertoire.

`checkEnvironment($database, $allowance)` exécute la première vérification seule : une console ne demande ainsi le nom
que si la restauration peut avoir lieu. `check($database, $allowance, $confirmed, $typed, $backup)` exécute les trois.
Chaque refus est une `RestoreRefused` qui dit quoi faire :

```
A restore is refused in the "prod" environment: set PHIL_BACKUP_ALLOW_PRODUCTION_RESTORE to the name of the database, shop, for this run alone.
Nothing was restored: add --confirm to replace the content of the postgresql database shop on db.internal with var/backup/shop-20261107-031500.dump.enc.
Nothing was restored: type the name of the database again, shop, with --database.
Nothing was restored: "Shop" is not the name of the database, shop.
```

## La sauvegarde faite d'abord

Avant une restauration qui a passé la garde, sauvegardez le contenu actuel — `Backups::create()` avec l'étiquette
`before-restore` — et ne restaurez qu'une fois cette sauvegarde écrite. Une restauration du mauvais jeu se défait alors
en restaurant le jeu étiqueté. La commande de `phil/backup-bundle` fait exactement cela.

La rotation ne touche jamais un jeu étiqueté : ces copies s'accumuleraient donc. Une fois une restauration réussie,
`Backups::keepLatest('before-restore', 3)` les supprime toutes sauf les trois plus récentes, et retourne les jeux
supprimés. Ne faites ce tri qu'après une réussite : une restauration qui a échoué à mi-chemin peut avoir modifié la base
de données, et la copie faite avant la tentative suivante contient ce contenu modifié.

## L'ordre d'une restauration

`restore($set, $media = true, $prune = false)` :

1. déchiffre l'archive des médias, si le jeu en a une et que les médias sont restaurés, et la lit en entier : une
   archive endommagée arrête la restauration **avant qu'on touche à la base de données** ;
2. déchiffre le fichier de base de données dans un fichier temporaire et le restaure avec l'outil de la plateforme ;
3. réécrit les fichiers médias, comme le décrit [media-files.md](media-files.md) ;
4. avec `$prune`, supprime les fichiers des répertoires de médias que l'archive ne contient pas.

Elle retourne une `Restoration` : `$mediaFiles`, le nombre de fichiers médias écrits, et `$removedFiles`, le nombre de
fichiers supprimés par l'élagage — tous deux à zéro pour une restauration de la base de données seule.

Une restauration de la base de données seule — `$media = false`, ou un jeu sans médias — laisse les répertoires de
médias tels quels.
