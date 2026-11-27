<!-- phil-dev source-sha256=a35b34aff509d4242bac65a6cbeb725b1b3082743fa9fa564ebdff415cda84cf -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/money` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Currency` : un code ISO 4217 que connaissent les données Unicode CLDR, avec ses décimales, son nom et son symbole
  localisés ; une instance par code, cherchée dans les données CLDR une fois par processus.
- `Money` : des unités mineures entières avec une devise ; `of()`, `zero()`, `ofDecimal()`, `sum()`, `toDecimal()`,
  `add()`, `subtract()`, `negate()`, `abs()`, `multiply()`, `compare()`, `isGreaterThan()`, `isLessThan()`, `equals()`
  et les tests de signe ; `allocate()` par ratios, au plus fort reste, et `split()` en parts égales, sans perdre une
  unité ni l'un ni l'autre ; une plage d'entiers symétrique, les dépassements refusés.
- `MoneyException`, implémentée par `UnknownCurrency`, `InvalidAmount` et `CurrencyMismatch`.
- `SchemaOrg` : la forme schema.org de la valeur de la bibliothèque, à intégrer dans du JSON-LD.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
