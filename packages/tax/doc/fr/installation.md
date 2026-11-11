<!-- phil-dev source-sha256=c29592d77d7be6b6ce8aa4399a78f3a7898e65802ec2bc0d237a60a7ebe3f795 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que la bibliothèque déclare et sur lequel elle est testée |
| `phil/money` | ^0.1 | `Money` et `Currency`, dans lesquels chaque base et chaque taxe sont comptées, et `CurrencyMismatch` |

## Installation

```sh
composer require phil/tax
```

La bibliothèque n'enregistre rien et ne lit aucune configuration. `MultiRateTaxCalculator` ne garde aucun état et ne
prend aucun argument de constructeur : construisez-le là où vous en avez besoin, ou déclarez-le comme service.
