<!-- phil-dev source-sha256=9f69ee5ca63d0e4c111b9b2a7726890d31e4a8cbfc70783ee2e2e189f79e8db3 -->
# Points d'extension

La bibliothèque est un seul type de valeur : elle ne déclare ni service ni port. Ce qui reste ouvert, c'est le nom des
genres.

| Point | Comment |
|---|---|
| Les genres de ressources | Choisis par l'application : toute clé conforme aux règles, `article`, `media.image`, un par genre de chose qu'elle désigne |
| Comment trouver la référence d'un objet | C'est le rôle de l'application : chaque paquet qui accepte des références — une corbeille, un index des usages, un calendrier — demande à l'application la référence d'un objet par un port qui lui est propre |
| Comment conserver une référence | `toString()` donne `kind:identifier` pour une seule colonne, `fromString()` la relit ; ou deux colonnes, à partir de `key` et de `id` |

Un genre fait partie des données : en renommer un oblige à mettre à jour chaque ligne qui le nomme, dans chaque paquet
qui conserve des références.
