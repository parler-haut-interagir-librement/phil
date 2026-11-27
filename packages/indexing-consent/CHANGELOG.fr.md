<!-- phil-dev source-sha256=111a8b02305ca9c13ceaebb9716c6b5bf2a01645ca0c48c2c7ef704ac4aee9c6 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/indexing-consent` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-12

### Ajouté

- `Indexing` : `Listed`, `NotIndexed` et `Unlisted` ; `allows()`, `robotsDirectives()`, `robots()`, `isStricterThan()`,
  `strictest()` et `allowing()`, les valeurs qu'un lecteur permet pour une requête sur la valeur stockée.
- `Reader` : les moteurs de recherche, le plan du site, les données structurées et les listes propres au site.
- `HasIndexingConsent`, le contrat d'un enregistrement public dont la portée est décidée par la personne qu'il concerne.
- `IndexingPolicy` : `of()`, `ofAll()`, `allows()` et `filter()`, un enregistrement sans le contrat étant listé, et
  `REQUEST_ATTRIBUTE`, sous lequel la requête d'une page porte le choix de l'enregistrement qu'elle montre.
- La documentation dans `doc/`, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
