<!-- phil-dev source-sha256=71d5a1177004512ff64d58b2eb3b1ce7f1260b6b629862105b55dfa80fc8990b -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/number-words` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.1-alpha.

Tests seulement.

### Modifié

- Le test des ordinaux pluriels allemands est sauté quand les données ICU de PHP n'ont pas de règle pour eux.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `NumberWords` : les cardinaux dits seuls ou accordés à un genre, les ordinaux dans leur genre et leur nombre, et les
  montants d'argent en lettres avec leurs unités et leurs sous-unités, dans une locale, par les règles d'épellation
  d'ICU ; une locale qu'ICU n'épelle pas, un jeu de règles manquant, des chiffres dans la réponse d'ICU et un ordinal
  négatif chacun refusé par une exception.
- `SpellingOptions` et `FrenchSpelling` : les traits d'union du français dans l'orthographe traditionnelle ou dans les
  rectifications de 1990, les traits d'union conditionnels d'ICU gardés ou laissés de côté, les formes verbeuses d'ICU.
- `CurrencyName`, et les noms de dix devises — `EUR`, `USD`, `CAD`, `AUD`, `NZD`, `GBP`, `CHF`, `JPY`, `MXN`, `BRL` — en
  français, en anglais, en espagnol, en allemand, en italien et en portugais ; les montants comme chaînes décimales,
  jamais arrondis.
- `Contract\LanguageRules`, `Language\AbstractLanguageRules` et les règles des six langues : le pluriel de `cents` et de
  `quatre-vingts` devant les grands noms, le singulier français de zéro et de un, `de` ou `d'` après `million` et sa
  famille, les partitifs de l'espagnol, de l'italien et du portugais, les mots entre unités et sous-unités et pour
  moins.
- Les exceptions sous `NumberWordsException` : `IntlUnavailable`, `UnsupportedLocale`, `MissingRule`, `NumberOutOfRange`
  et `InvalidAmount`.
- Les tests unitaires de chaque langue, correction et refus.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README ; `CONTRIBUTING.md`, `SECURITY.md` et
  `CODE_OF_CONDUCT.md`.
