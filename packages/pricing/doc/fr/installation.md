<!-- phil-dev source-sha256=415d4aeebb9237fde5aba7e528f95b77d2a28ceabea7f67084007f99bef919bd -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée ; `\RoundingMode` vient de PHP 8.4 |
| `phil/money` | ^0.1 | `Money` et `Currency`, dans lesquels sont comptés chaque prix, coût et remise, et `CurrencyMismatch` |

## Installer

```sh
composer require phil/pricing
```

La bibliothèque n'enregistre rien et ne lit aucune configuration. `MarginCalculator`, `PriceCalculator` et
`DiscountCascade` ne gardent aucun état et ne prennent aucun argument de constructeur : construisez-les là où il le
faut, ou déclarez-les comme services. `PriceEnding` et `PriceEndingBands` sont des valeurs immuables, construites une
fois à partir des réglages de l'application et sûres à partager entre les requêtes.
