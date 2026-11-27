<!-- phil-dev source-sha256=d8fa58cc1fc770fc16c9b1686aea68e3e944dda4584a3354540bef607c3ac549 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/slug` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-24

### Modifié

- Exige la dernière mineure de chaque dépendance (`symfony/translation-contracts` 3.8).

## 0.1.1-alpha - 2026-11-17

Documentation seulement.

### Modifié

- `CONTRIBUTING.md` dit ce que fait le paquet et comment y travailler, sans la formulation de ses premières ébauches.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Slug` (`fromString()`, `equals()`, `toString()`) et `InvalidSlug`.
- `Slugger`, l'interface que chaque générateur de slugs et chaque décorateur implémente.
- `TransliteratingSlugger` : la translittération en ASCII dans une locale, des mots en minuscules joints par des traits
  d'union, coupés à un mot.
- `LeadingWordsRemoved` : un décorateur qui retire un mot initial d'une liste ; `LeadingWordsRemoved::checkWord()`, la
  règle d'un mot initial, appliquée sans construire de générateur de slugs.
- `Sluggers`, les générateurs de slugs nommés d'une application, et `UnknownSlugger`.
- La documentation dans `doc/`, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
