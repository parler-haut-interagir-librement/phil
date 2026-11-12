<!-- phil-dev source-sha256=5c520db7ba26cff2803cbbfa606f15b8f50b9a5588d100bb823de30f5bb6b9a5 -->
# Installation

```sh
composer require phil/search-synonyms
```

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que le paquet déclare |
| `ext-mbstring` | toute | Les expressions en minuscules |
| `phil/search-bundle` | facultatif | Une recherche qui lit les synonymes d'un magasin — voir [Magasins](stores.md) |

Le paquet n'a aucune autre dépendance et n'a besoin d'aucune configuration : un magasin se construit avec son
répertoire, ou se donne au conteneur de l'application comme n'importe quel service.
