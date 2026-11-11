<!-- phil-dev source-sha256=62048de766ac9b6de00482331bcd3ca329fe886f31559a540f2cf4150715f3b6 -->
# Règles @

Par défaut, la politique permet `@media`, `@supports`, `@page` et `@font-face` (`AtRuleKind`) ; `withoutAtRules()` les
retire, et `withAtRules(AtRuleKind::Import)` ajoute `@import`. Toute autre règle @ — `@charset`, `@namespace`,
`@keyframes`, `@layer`, `@container`, `@property`, `@font-feature-values` — est retirée (`AtRule`), et `@import` l'est
sauf si la politique le permet (`Import`). Une règle @ dont le prélude est refusé est retirée en entier (`Prelude`).

## `@media`

Le prélude est une liste de requêtes média. Chacune contient les mots-clés `only`, `not`, `and` et `or`, les types de
médias de la politique (`all`, `print`, `screen`) et des caractéristiques entre parenthèses, faites de noms, de
nombres, de dimensions, de pourcentages, de deux-points, de rapports et de comparaisons : `(min-width: 30em)`,
`(400px <= width <= 700px)`, `(aspect-ratio: 16/9)`. Un prélude vide correspond à tous les médias. Le bloc contient des
règles, vérifiées comme au niveau supérieur : des règles de style et les règles @ permises.

## `@supports`

Le prélude est une condition : `not`, `and`, `or`, des conditions entre parenthèses et `selector()`, sans URL, sans
chaîne invalide et sans bloc `{}`. Le bloc contient des règles.

## `@page`

Le prélude est une liste de sélecteurs de page : un nom de page facultatif suivi de `:first`, `:left`, `:right` ou
`:blank`, sans espace entre eux — `@page :first`, `@page chapter:left, index`. Le bloc contient des déclarations — les
propriétés de la politique et les descripteurs `size`, `marks`, `bleed` et `page-orientation` — et les règles @ des
seize boîtes de marge, chacune avec ses propres déclarations et un prélude vide :

`@top-left-corner`, `@top-left`, `@top-center`, `@top-right`, `@top-right-corner`, `@bottom-left-corner`,
`@bottom-left`, `@bottom-center`, `@bottom-right`, `@bottom-right-corner`, `@left-top`, `@left-middle`,
`@left-bottom`, `@right-top`, `@right-middle`, `@right-bottom`.

```css
@page :first {
  size: A4;
  margin: 2cm 2cm 3cm;

  @bottom-center {
    content: counter(page) " / " counter(pages);
  }
}
```

`@page` est permis dans `@media` et dans `@supports`.

## `@font-face`

Le prélude doit être vide. Le bloc contient les descripteurs `font-family`, `src`, `font-style`, `font-weight`,
`font-stretch`, `font-display`, `unicode-range`, `font-feature-settings`, `font-variation-settings`,
`ascent-override`, `descent-override`, `line-gap-override` et `size-adjust`. Quand un descripteur est écrit deux fois,
le dernier reste.

Chaque source de `src` est soit `local()` avec un nom, soit une URL suivie de `format()` et de `tech()` facultatifs.
Une URL passe par le réécrivain avec `UrlKind::Font`. Une source refusée, ou d'une autre forme, est retirée (`Url`,
`FontSource`) et les autres restent. Une règle restée sans `font-family` ou sans source est retirée (`FontSource`).

```css
@font-face {
  font-family: "Body";
  src: local("Body Regular"), url(body.woff2) format("woff2");
  font-display: swap;
}
```

## `@import`

Permis seulement par `withAtRules(AtRuleKind::Import)`, et seulement au début de la feuille de style : un `@import`
après toute autre règle, dans une règle de groupe ou avec un bloc est retiré (`Import`). Son prélude est une URL, sous
forme de chaîne ou d'`url()`, puis une condition `supports()` facultative qui ne contient aucune URL, puis des requêtes
média lues comme les lit `@media` ; une couche n'est pas permise. Un prélude d'une autre forme est retiré (`Prelude`).
L'URL passe par le réécrivain avec `UrlKind::StyleSheet` ; refusée, elle retire la règle (`Url`). Les règles gardées
s'écrivent en premier, sous forme de valeurs `Sheet\ImportRule` dans `StyleSheet::$imports`.

```css
@import "theme.css" supports(display: grid) print;
/* @import url("/media/42/theme.css") supports(display: grid) print; */
```

## Les règles imbriquées dans une règle de style

Une règle de style peut contenir, à côté de ses déclarations, des règles de style dont les sélecteurs lui sont
relatifs ([Sélecteurs](selectors.md)), et des règles `@media` et `@supports`. Les préludes de celles-ci sont vérifiés
comme au premier niveau, et leurs blocs contiennent des déclarations pour les éléments du parent et leurs propres
règles imbriquées. Toute autre règle @ y est retirée (`AtRule`). Chaque règle ou groupe imbriqué compte comme un
niveau ; au-delà du `maxNesting` de la politique, trois par défaut, il est retiré (`Nesting`). Les règles imbriquées
sont lues comme CSS Nesting lit un bloc : un élément qui commence par un nom est une déclaration, sauf s'il n'a pas de
deux-points, ou s'il a un bloc `{}` à côté d'autres valeurs, comme dans `a:hover { … }`.

```css
.card {
  color: #333;

  & h2 {
    margin: 0;
  }

  @media print {
    color: black;
  }
}
```

Les déclarations d'une règle s'écrivent avant ses règles imbriquées, comme les navigateurs ont d'abord lu le CSS
imbriqué.
