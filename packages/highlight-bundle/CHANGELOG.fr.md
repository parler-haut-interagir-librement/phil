<!-- phil-dev source-sha256=8c6734abd215941a14c9e40b028e43a8815fa5244ff2e725dde9e8fab8127a01 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/highlight-bundle` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## Non publié

La prochaine version est la 0.1.2-alpha.

Développement seulement.

### Modifié

- Les prérequis de développement demandent la dernière mineure de chaque dépendance (`twig/twig` 3.31).
- Les prérequis de développement prennent `twig/twig` 3.31.1 ou plus récent, qui corrige les avis sur son bac à sable
  publiés le 2026-11-24.

## 0.1.1-alpha - 2026-11-15

### Corrigé

- La feuille de style est associée dans la configuration d'AssetMapperBundle, sous `asset_mapper`, et seulement quand ce
  bundle est enregistré : une application avec `symfony/asset-mapper` installé mais AssetMapperBundle laissé de côté
  n'échoue plus à compiler son conteneur sur l'alias `framework.asset_mapper`.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- Le service `Highlighter` : les langages de `phil/highlight`, les services qui implémentent `Language`, autoconfigurés
  avec l'étiquette `phil_highlight.language`, chacun à la place d'un langage du même nom, et les alias configurés.
- `PhilHighlightBundle` et la configuration `phil_highlight` : `aliases`, d'autres noms pour un langage, vérifiés comme
  le surligneur les vérifie ; et l'intégration `ujo`, qui peut être désactivée.
- `HighlighterPass` et `AliasedLanguage` : les langages étiquetés et les alias ajoutés une fois chaque bundle chargé, et
  le `HtmlRenderer` de `phil/ujo-bundle` décoré avec le surligneur, si bien que les blocs de code de chaque document UJO
  sont colorés sur le serveur.
- Le filtre Twig `highlight` : le code échappé et enveloppé dans des `span` `hl-` dans un langage, ou seulement échappé
  pour un langage que le surligneur ne connaît pas ou en l'absence de langage ; avec `lines`, ligne par ligne, numéroté,
  les plages surlignées et les annotations des commentaires lues.
- `assets/styles/highlight.css` : un thème des classes `hl-` et des blocs qui les contiennent, clair et sombre selon le
  système ou `data-theme`, clair sur papier, ses couleurs des propriétés personnalisées qui respectent le contraste WCAG
  AA, les lignes marquées signalées par une teinte, une barre et un signe, associé pour AssetMapper comme
  `phil-highlight/highlight.css`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
