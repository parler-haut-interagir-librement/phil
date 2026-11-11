<!-- phil-dev source-sha256=9230a29da5843800c64ee36266e91213b93f7f3769854a7b6557e22bd3f33b20 -->
# Rotation

## Les règles

`RotationPolicy(last, daily, weekly, monthly)` garde :

| Règle | Garde |
|---|---|
| `last: n` | les `n` jeux les plus récents |
| `daily: n` | le jeu le plus récent de chacun des `n` derniers jours qui en ont un |
| `weekly: n` | le jeu le plus récent de chacune des `n` dernières semaines ISO qui en ont un |
| `monthly: n` | le jeu le plus récent de chacun des `n` derniers mois qui en ont un |

Un jeu gardé par une règle quelconque est gardé ; les autres ont expiré. C'est le schéma grand-père-père-fils : avec
`last: 3, daily: 7, weekly: 4, monthly: 12` et une sauvegarde chaque nuit, le répertoire contient environ deux douzaines
de jeux qui remontent sur un an.

Chaque compte vaut zéro ou plus, et l'un d'eux au moins est supérieur à zéro ; une rotation qui ne garderait rien est
refusée avec `InvalidBackupValue`. Le jeu le plus récent est toujours gardé, puisque chaque règle en garde au moins un.

## Périodes

Les jours, les semaines et les mois sont ceux de l'UTC, l'heure des noms de fichiers : un changement de fuseau horaire
ou d'heure d'été du serveur ne fait donc passer aucune sauvegarde d'un jour à un autre. Les semaines sont des semaines
ISO, du lundi au dimanche : une sauvegarde du dimanche appartient à la semaine du lundi qui la précède. Une période sans
sauvegarde n'est pas comptée : après une semaine de panne, `daily: 7` garde encore sept jeux.

## Ce qui est en rotation

`expired($sets)` donne les jeux expirés, du plus ancien au plus récent ; `Backups::prune()` les supprime et les
retourne. Seuls les jeux **sans étiquette** sont en rotation : la copie faite avant une restauration, une copie
anonymisée ou une copie faite avant une migration reste jusqu'à ce qu'elle soit supprimée à la main, ou par
`Backups::keepLatest()` pour une étiquette. Les fichiers du répertoire qui ne sont pas des jeux ne sont jamais touchés.
