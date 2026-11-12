<!-- phil-dev source-sha256=a68d59296816f43c10e808947ab0d617511d497e20a16fc4af442eb8180544fd -->
# Installation

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée |
| `ext-mbstring` | toutes | Les textes coupés à une longueur en caractères |
| `phil/recurrence` | ^0.1@dev | `Weekday`, les jours de la semaine |

```sh
composer require phil/almanac
```

La bibliothèque n'a aucune configuration et ne garde rien : chaque date est calculée au moment où elle est demandée.
Avec Symfony, `phil/almanac-bundle` lit les définitions dans la configuration et dans la base de données.
