<!-- phil-dev source-sha256=cf67b7d9f7caace3f1d11c686d434049ba9f0c64e59850e1c01b94d9cb3a94c8 -->
# Installation

## Prérequis

| Prérequis | Version | Pourquoi |
|---|---|---|
| PHP | 8.5 ou plus récent | Le plancher que déclare la bibliothèque et sur lequel elle est testée |
| `ext-intl` | toutes | La locale par défaut (`Locale::getDefault()`) de `name()` et `symbol()` appelés sans locale |
| `symfony/intl` | ^8.2 | Les données des devises d'Unicode CLDR : les codes qui existent, leur nombre de décimales, leurs noms et leurs symboles |

`symfony/intl` est fourni avec les données de chaque locale ; l'extension ne fournit que la locale par défaut.

## Installer

```sh
composer require phil/money
```

La bibliothèque n'enregistre rien et ne lit aucune configuration. Dans une application Symfony, `phil/money-bundle`
ajoute des formulaires, la validation, la mise en forme Twig et des colonnes Doctrine pour ses types.
