<!-- phil-dev source-sha256=de509d5feec4f05799a591fa66832d589c8ef7ac325d8632da8906b97e7f5435 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/locale-dimensions` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-14

### Ajouté

- `DeclaredLocales::fromArray()` : une locale servie déclarée avec `null` obtient la chaîne par défaut — les parents que
  le `LocaleFallbackProvider` de Symfony dérive d'ICU (`fr_CA → fr`), puis les replis par défaut donnés comme second
  argument, chacun gardé seulement s'il est servi ; un repli par défaut qui n'est pas servi est refusé.
- `Locale::tryFromString()` : la locale, ou `null` pour une chaîne qui ne peut pas en être une, pour une locale lue
  d'une URL, d'un formulaire, d'un en-tête ou d'une rangée stockée.

### Modifié

- Le paquet exige `symfony/translation` 8.2 ou plus récent, pour `LocaleFallbackProvider`.

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `Locale` : une langue et une région facultative avec une seule graphie (`fr`, `fr_CA`), `sharesLanguageWith()`,
  `toLanguageTag()` pour BCP 47 et `equals()`.
- `Version` : une version comptée à partir de un, `first()`, `of()`, `next()`, `isAfter()` et `equals()`.
- `FallbackChain` : les locales déclarées essayées pour une locale demandée, `resolve()` d'après les locales qui ont
  quelque chose à servir, et `leavesRequestedLanguage()`.
- `DeclaredLocales` : les locales servies et leurs chaînes à partir d'un tableau déclaré, un repli qui n'est pas servi
  étant refusé.
- `Exception\InvalidLocaleDimension`, levée par chaque valeur refusée à sa construction.
- La documentation dans `doc/`, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
