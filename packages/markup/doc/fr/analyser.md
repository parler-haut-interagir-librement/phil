<!-- phil-dev source-sha256=1b711220bf3e45330da919e3c3a4b64ed7957fbd9723d4c138b322c25f7b16dd -->
# L'analyseur

Les lecteurs sont écrits sur un seul analyseur, à la manière d'un analyseur lexical écrit à la main : un curseur qui
avance dans le texte et des règles qui reconnaissent chacune un type de jeton, sans expressions régulières là où un
curseur peut faire le travail.

## Le curseur

`Lexer\Cursor` lit un texte octet par octet ; les caractères de chaque syntaxe sont en ASCII, et les méthodes qui
consomment des suites consomment en entier un caractère de plusieurs octets.

| Méthode | Ce qu'elle fait |
|---|---|
| `current()`, `peek($offset)` | L'octet à la position, ou à un décalage (négatif pour regarder en arrière) ; `''` hors du texte |
| `comesNext($text)` | Si le texte à la position commence par celui-ci |
| `consume($text)` | Avance au-delà du texte quand il vient ensuite |
| `consumeWhile($characters)`, `consumeUntil($characters)` | La suite d'octets parmi les caractères, ou hors d'eux, consommée |
| `consumeThrough($delimiter)` | Le texte jusqu'à un délimiteur, consommé avec lui ; `null`, sans rien consommer, s'il ne vient jamais |
| `advance($length)`, `rest()` | Des octets, ou tout ce qui reste, consommés |
| `position()`, `moveTo($position)` | Où est le curseur ; comment une règle qui ne correspond pas rend le texte |

`Lexer\Slugger` produit les ancres d'un document, uniques, comme GitHub les écrit ; il se souvient du suffixe atteint
par chaque texte, pour que de nombreux titres au même texte prennent chacun un temps constant.

## Deux passes

Le Markdown se lit en deux passes, pour qu'une référence de lien, une note de bas de page ou une variable puisse être
définie après le texte qui l'utilise.

1. **Les blocs.** `BlockParser` lit les lignes. Un conteneur rassemble ses lignes (celles d'une citation sans leur
   `>`, celles d'un élément de liste sans leur indentation, celles entre `{{` et `}}`) et les lit comme un document à
   part. Chaque bloc est un bloc UJO dont le contenu en ligne n'est pas encore lu : un `Inline` de morceaux de
   Markdown.
2. **Le contenu en ligne.** `Resolver` lit chaque `Inline` avec `InlineParser`, coupe les paragraphes autour de leurs
   images, donne leurs ancres aux titres, et ajoute à la fin les notes de bas de page référencées.

`InlineParser` fait l'analyse lexicale avec une règle par type de jeton, chacune déclenchée par ses propres
caractères ; le texte entre les jetons est échappé. Les suites de délimiteurs de l'emphase sont gardées comme jetons,
puis appariées comme CommonMark les apparie : à partir du premier fermant, avec l'ouvrant le plus proche du même
caractère.

| Règle | Commence par | Reconnaît |
|---|---|---|
| `EscapeRule` | `\` | un caractère de ponctuation échappé, un saut de ligne |
| `EntityRule` | `&` | une entité HTML |
| `CodeSpanRule` | `` ` `` | un segment de code |
| `AngleRule` | `<` | un lien automatique, ou du HTML brut |
| `LinkRule` | `[`, `!` | un lien, une image, une référence de note de bas de page |
| `BareLinkRule` | `h`, `w` | une adresse sans crochets |
| `DelimiterRule` | `*`, `_`, `~`, `=` ; `^` pour un dialecte qui l'ajoute | une suite de délimiteurs, et si elle ouvre ou ferme |

Les règles d'un `MarkdownDialect` viennent après celles-ci, sur leurs propres caractères : voir
[Points d'extension](extension-points.md#dialectes-de-markdown).

Le BBCode se lit aussi en deux passes. `Reader\BBCode\Parser` analyse les balises avec le curseur et en fait un arbre,
en fermant ce qui reste ouvert ; `Builder` fait de l'arbre des blocs UJO dont le contenu en ligne est déjà du HTML, et
le `Resolver` du lecteur Markdown coupe les paragraphes autour des images, donne leurs ancres aux titres et ajoute les
notes de bas de page. Les encadrés, les blocs de statistiques et les styles de page sont ceux du lecteur Markdown,
produits par son `Curly`. Voir [BBCode](bbcode.md).

Les wikis partagent un seul lecteur, `Reader\Wiki`, auquel chaque wiki donne sa syntaxe. `Containers` place en une
passe les balises des conteneurs sur des lignes à elles ; `Engine` lit les lignes et en fait des blocs (les conteneurs
et les balises de pages qu'il connaît, les autres blocs demandés à la syntaxe du wiki, les paragraphes) ; `Spans` lit
le texte en ligne avec le curseur, les caractères du wiki étant reconnus par sa syntaxe, et les marques d'emphase
appariées au fil de la lecture. Le `Resolver` du lecteur Markdown place ensuite les images, donne leurs ancres aux
titres et ajoute les notes de bas de page. Voir [Les wikis](wikis.md). Le lecteur HTML parcourt l'arbre que construit
l'analyseur HTML5 de PHP ; voir [HTML](html.md).

## Limites

Un texte venu de n'importe qui se lit en un temps linéaire dans sa longueur. Les conteneurs s'imbriquent sur cinq
niveaux, les crochets d'un lien sur 32, les parenthèses d'une adresse sur 32 et les segments `{{ }}` sur 8 ; plus
profond, les marqueurs sont du texte. Comme dans CommonMark, la recherche d'un ouvrant qui a échoué n'est pas refaite
sous le fermant pour lequel elle a échoué. La deuxième passe remplace les marques d'images en coupant le HTML une seule
fois, et ajoute les notes de bas de page en une seule passe sur elles. Les balises BBCode s'imbriquent sur 32 niveaux ;
ses limites sont dans [BBCode](bbcode.md).

Les blocs d'un document sont ajoutés sur place, pour qu'un texte de nombreux paragraphes, titres, listes de définitions
ou listes prenne un temps qui croît avec leur nombre ; les listes de définitions qui se suivent sont jointes en une
seule passe. Le lecteur HTML compte une seule fois les enfants d'un élément, la première fois que le chemin de l'un
d'eux est écrit dans une perte, pour que les pertes de nombreux frères prennent aussi un temps qui croît avec leur
nombre.

## Le plafond d'une source

Chaque lecteur, et `Adapter\DefaultCompiler`, refuse une source plus longue que son plafond avant de la lire : il lance
`Exception\InputTooLarge`, qui donne la longueur de la source et le plafond dans `$bytes` et `$limit`, et ne coupe
jamais la source pour qu'elle y tienne. Le plafond se compte en octets, chaque caractère UTF-8 comptant chacun de ses
octets. C'est l'argument `maxBytes` de chaque constructeur, `MarkupReader::DEFAULT_MAX_BYTES` (1 Mio) par défaut, sauf pour UJO :
`UjoReader::DEFAULT_MAX_BYTES` est le plafond du décodeur de `phil/ujo`, 4 Mio, puisque le JSON d'un document pèse plus
que le texte qu'il contient :

```php
$reader = new MarkdownReader(maxBytes: 262_144);                             // 256 KiB
$compiler = new DefaultCompiler(maxBytes: 2 * 1_048_576);                   // 2 MiB, in every format but UJO
$compiler = new DefaultCompiler(maxBytes: 262_144, ujoMaxBytes: 1_048_576); // 256 KiB, and 1 MiB for UJO
```

`DefaultCompiler` vérifie la source avant de la compiler, dans tous les formats (y compris le HTML et le texte brut,
qu'il compile sans lecteur), donne son plafond aux lecteurs que retourne `reader()`, et place derrière lui le lecteur
du format d'un module complémentaire. Une source UJO a un plafond à elle, `ujoMaxBytes`, que le compilateur donne à son
lecteur UJO et au décodeur de `phil/ujo` qui le suit, de sorte que les deux refusent à la même longueur. `Markup` refuse
ce que refuse son compilateur. Une source UJO est refusée par le plafond de `UjoReader` avant que le décodeur de
`phil/ujo` la voie.

Les purificateurs ont aussi un plafond, 4 Mio par défaut : voir
[Le plafond d'un HTML purifié](reference.md#le-plafond-dun-html-purifie).

Le plafond borne le temps et la mémoire que prend une lecture, quelle que soit sa source, avant les limites du document
que le normaliseur de `phil/ujo` vérifie ensuite : le nombre de blocs, la longueur d'un champ HTML. 1 Mio contient bien
des fois un long chapitre écrit à la main ; une application qui reçoit des textes plus longs le relève, celle qui
reçoit des commentaires l'abaisse. Un document UJO du même chapitre, avec ses identifiants de blocs et son balisage, en
exige davantage.
