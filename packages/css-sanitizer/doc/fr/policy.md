<!-- phil-dev source-sha256=a5573be440d7f068f43f3979ed5751d75ae08525a023f8438896ae8070de1478 -->
# Politique

Une `Policy` dit ce qu'une feuille de style peut contenir. Elle est immuable : chaque `with…()` renvoie une nouvelle
politique. Les noms sont comparés en minuscules ASCII, comme CSS les compare.

## La politique par défaut

`new Policy()` permet ce dont a besoin une page mise en page pour l'écran ou pour l'impression :

| Liste | Valeur par défaut |
|---|---|
| Propriétés (`DEFAULT_PROPERTIES`) | Boîte (marges, remplissages, bordures, rayon, contour, ombre), taille, affichage et flottement, flex et grille, colonnes, fragmentation (`break-*`, `page-break-*`, `orphans`, `widows`, `page`), contenu généré (`content`, `quotes`, compteurs, listes, `string-set`, signets, notes de bas de page), texte et polices, arrière-plan, opacité, filtres, transformations, `clip-path`, tableaux |
| Fonctions (`DEFAULT_FUNCTIONS`) | Couleurs (de `rgb()` à `light-dark()`), mathématiques (`calc()`, `min()`, `max()`, `clamp()`, …), grilles (`repeat()`, `minmax()`, `fit-content()`), dégradés, `counter()`, `counters()`, `attr()`, `string()`, `content()`, `leader()`, `target-counter()`, `target-counters()`, `target-text()`, transformations, filtres |
| Pseudo-classes (`DEFAULT_PSEUDO_CLASSES`) | Structurelles (`:root`, `:empty`, `:first-child`, `:nth-child()` …), `:not()`, `:is()`, `:where()`, `:lang()`, `:dir()`, `:link`, `:hover`, `:focus`, `:focus-visible`, `:focus-within`, `:active`, `:target` |
| Pseudo-éléments (`DEFAULT_PSEUDO_ELEMENTS`) | `::before`, `::after`, `::first-letter`, `::first-line`, `::marker`, `::selection`, `::footnote-call`, `::footnote-marker` |
| Attributs (`DEFAULT_ATTRIBUTES`) | `lang`, `dir` |
| Types de médias (`DEFAULT_MEDIA_TYPES`) | `all`, `print`, `screen` |
| Règles @ | `@media`, `@supports`, `@page`, `@font-face` ; pas `@import` |
| Imbrication | Trois niveaux de règles imbriquées dans une règle de style |
| Réécrivain d'URL | `RefuseUrls` : toutes les URL refusées |

Elle refuse, entre autres : `position`, `top`, `inset` et `z-index`, qui peuvent poser une boîte par-dessus la page
propre à l'application ; `cursor`, les transitions et les animations ; `:visited`, `:has()` et `:checked` ; tout
sélecteur d'attribut sauf `lang` et `dir`, pour qu'un sélecteur ne puisse pas lire la valeur d'un champ.

## La modifier

```php
$policy = new Policy()
    ->withProperties('position', 'z-index')       // added to the list
    ->withoutProperties('filter')                 // taken out of it
    ->withFunctions('env')
    ->withoutFunctions('attr')
    ->withPseudoClasses('checked')
    ->withoutPseudoClasses('hover')
    ->withPseudoElements('placeholder')
    ->withoutPseudoElements('selection')
    ->withAttributes('data-*', 'title')           // `data-*` allows every data- attribute
    ->withoutAttributes('dir')
    ->withMediaTypes('print')                     // replaces the list
    ->withoutAtRules(AtRuleKind::FontFace)
    ->withAtRules(AtRuleKind::FontFace)
    ->withUrlRewriter($rewriter)
    ->withScope('.user-styles')
    ->withImportant(false)
    ->withCustomProperties(true)
    ->withMaxLength(65_536)
    ->withMaxDepth(16)
    ->withMaxNesting(2);
```

Les listes sont publiques, sous forme d'ensembles : `$policy->properties['color']` vaut `true`.

## Jamais permis

`withProperties()` lève `InvalidPolicy` pour `behavior`, `-ms-behavior` et `-moz-binding` (`DENIED_PROPERTIES`), qui
exécutent du code ou lient un comportement. `withFunctions()` lève l'exception pour `expression()`, `url()`, `src()`,
`image()`, `image-set()`, `-webkit-image-set()`, `cross-fade()`, `-webkit-cross-fade()`, `element()`,
`-moz-element()` et `paint()` (`DENIED_FUNCTIONS`), qui exécutent du code ou chargent une ressource à partir d'une
chaîne. `url()` et `image-set()` sont plutôt traitées par le réécrivain : chaque image d'`image-set()` est réécrite
comme toute `url()` ([URL](urls.md)).

## `@import`

`AtRuleKind::Import` est la seule règle @ que la politique par défaut laisse de côté. `withAtRules(AtRuleKind::Import)`
garde les règles `@import` qui ouvrent une feuille de style, leur URL réécrite avec `UrlKind::StyleSheet`. Les feuilles
de style qu'elles chargent ne sont pas assainies par ce passage : le réécrivain ne renvoie donc que celles auxquelles
l'application fait confiance. Voyez [Règles @](at-rules.md).

## La portée

`withScope('.user-styles')` place chaque règle de style sous ce sélecteur : `p, h1` devient `.user-styles p,
.user-styles h1`, aussi dans `@media` et `@supports`. Les règles de page ne sont pas soumises à la portée, ni les règles
imbriquées : leurs sélecteurs sont relatifs à un parent qui l'est déjà, et `&` ne peut pas en sortir
([Sélecteurs](selectors.md)). La portée appartient à l'application et s'écrit telle qu'elle est donnée. Un sélecteur qui
commence par `:root`, `html` ou `body` ne correspond à rien sous la portée ; l'application décide si ses utilisateurs en
ont besoin.

## `!important` et propriétés personnalisées

`withImportant(false)` retire l'indicateur de chaque déclaration, qui reste, et liste chacune comme un retrait
`Important`.

`withCustomProperties(true)` permet les propriétés personnalisées (`--accent: #702`) et `var()`. Leurs valeurs sont
vérifiées comme toutes les autres : une propriété personnalisée ne peut porter ni une URL refusée par le réécrivain, ni
une fonction que la politique ne permet pas.

## L'imbrication

`maxNesting`, 3 par défaut, est le nombre de niveaux de règles qui peuvent s'imbriquer dans une règle de style, à la
manière de CSS Nesting. Une règle `@media` ou `@supports` imbriquée dans une règle de style compte comme un niveau.
Une règle d'un niveau de plus est retirée (`Nesting`) avec ce qu'elle contient, et son parent reste.
`withMaxNesting(0)` retire toute règle imbriquée. La valeur ne peut pas être négative.

## Les limites

`maxLength`, un mébioctet par défaut, est la plus longue source acceptée, en octets. `maxDepth`, 32 par défaut, est
l'imbrication la plus profonde de blocs et de fonctions. Une source qui dépasse l'une ou l'autre est refusée en entier,
avec un seul retrait `TooLong` ou `TooDeep`. Les deux doivent valoir au moins 1. L'assainisseur lit environ un
mébioctet en deux secondes.
