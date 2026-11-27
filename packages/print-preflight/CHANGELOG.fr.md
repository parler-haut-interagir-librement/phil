<!-- phil-dev source-sha256=c150a7e1c0e1108168867b7167eaf7e5fe42e6c578eb0c3a6014321429672610 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `phil/print-preflight` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `Length`, `Size`, `Rectangle` et `Edge` : des longueurs gardées en points et construites à partir de points, de
  millimètres, de pouces ou de pixels CSS, comparées à un demi-point près ; des formats finis avec A4, A5 et Letter ;
  des cadres mesurés depuis le coin supérieur gauche de la zone de coupe, ou convertis des coordonnées PDF avec le fond
  perdu de la page.
- `Document`, `Page`, `PlacedImage`, `Box`, `UsedFont` et `ColourSpace` : le modèle d'un document à imprimer — des pages
  avec leur format fini et leur fond perdu, des images avec leur taille en pixels, leur cadre, leur taille mise à
  l'échelle et leur résolution effective, d'autres boîtes, des éléments à fond perdu ou non, la couverture d'encre, la
  surimpression, l'épaisseur des traits et la transparence quand l'application les connaît, des polices avec leurs
  permissions d'intégration de `phil/fonts`, des espaces de couleur lus des noms PDF.
- `PrintProfile` et `ColourWorkflow` : les formats finis d'un imprimeur, le format uniforme, le fond perdu, la marge de
  sécurité, les résolutions minimale, cible et maximale, le minimum, le maximum et le multiple du nombre de pages, le
  flux de couleurs et les tons directs, la couverture d'encre maximale, l'épaisseur minimale des traits, la
  surimpression et la transparence, avec la gravité de chaque règle fixée ou ignorée par son code ; les règles
  contradictoires refusées.
- `Severity`, `Rule`, `Finding` et `Report` : erreurs, avertissements et avis, les codes et les gravités par défaut des
  règles intégrées, des constats avec une phrase en anglais, une page, un sujet et des paramètres, un rapport ordonné
  d'abord le document puis page par page, avec `passes()`, `isClean()`, des filtres et `toArray()`.
- `Check\PageCount`, `Check\PageGeometry`, `Check\Fonts`, `Check\ImageResolution`, `Check\Colours`, `Check\InkCoverage`,
  `Check\Overprint`, `Check\Transparency`, `Check\Hairlines` et `Check\Placement` : les vérifications intégrées, et
  `Check\Check` pour celles d'une application.
- `Preflight` : les vérifications exécutées dans l'ordre sur un document, les gravités et les règles ignorées du profil
  appliquées, une vérification ajoutée avec `withCheck()`.
- `PrintPreflightException`, implémentée par `InvalidDocument` et `InvalidProfile`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
