<!-- phil-dev source-sha256=fb9806b4d28b18de8eaf7c20c75160445c49d6a1b8d9e1d4d260855689f5e8d2 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/content-warnings` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Catalogue`, `Category`, `Theme` et `LocalizedText` : des thèmes par clé stable, groupés en catégories, étiquetés et
  décrits par locale, un libellé lu dans sa locale, sa langue, les replis, puis le premier donné ; un catalogue de 200
  catégories et de 2 000 thèmes au plus.
- `CatalogueLoader`, qui construit un catalogue à partir d'une définition ou en applique une sur un catalogue —
  catégories et thèmes ajoutés ou fusionnés par locale, un thème déplacé vers une autre catégorie, des thèmes et des
  catégories retirés, ou tout remplacé — à partir d'un tableau, d'un fichier PHP ou d'un fichier JSON de 2 Mo au plus ;
  `Key`, la règle de chaque clé.
- `BaseCatalogue` : environ soixante-dix thèmes en douze catégories, étiquetés en anglais, en français, en espagnol, en
  allemand, en italien et en portugais, dans `resources/base-catalogue.php`.
- `ContentWarning`, `ContentWarnings` et `Severity` : un avertissement par thème avec une gravité facultative et une
  note de 500 caractères, 100 avertissements par contenu, gardés comme tableau et relus par `fromArray()`.
- `Display`, `Preference` et `WarningPreferences` : l'affichage d'un lecteur par thème, par catégorie et par défaut,
  chacun à partir d'une gravité facultative, replié par défaut, gardés comme tableau et relus par `fromArray()`.
- `WarningMatcher` et `Decision` : l'affichage le plus strict des avertissements d'un contenu, et les avertissements qui
  l'ont donné.
- `Exception\ContentWarningsException`, `Exception\InvalidCatalogue`, `Exception\InvalidWarning` et
  `Exception\UnreadableWarnings`.
- Le banc d'essai `WarningMatcherBench`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
