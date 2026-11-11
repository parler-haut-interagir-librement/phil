<!-- phil-dev source-sha256=6decfa8024616e93ca07d6cc77b1a7c5ca226e221fd2da676e86f11d6a75e990 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `Reader\Markdown\MarkdownReader` | Chaque construction de CommonMark et des extensions de GitHub, notes de bas de page, attributs, listes de définitions, ancres, `[TOC]`, préambule, blocs `:::`, la syntaxe des pages, HTML brut gardé et échappé ; chaque source comparée au HTML que produit son document UJO |
| Blocs `{{ }}` | Les segments qui sont des blocs, les attributs sur les blocs, les blocs de statistiques, les encadrés, les couvertures, les citations, les crédits, les tables de classe, les tables des matières |
| `Reader\Markdown\BaseDialect` et les points d'un dialecte | Un dialecte qui ne change rien se lit comme du Markdown simple ; un dialecte pour chaque point (règles en ligne et de bloc, une syntaxe désactivée, métadonnées, définitions, la marque d'une définition et d'un paragraphe) lit sa syntaxe, et ne garde rien entre deux lectures |
| `Adapter\DefaultCompiler` et les formats des modules complémentaires | Un format lu par son fournisseur au niveau demandé, un format qu'aucun lecteur ne connaît, les noms qu'un module complémentaire ne peut pas prendre |
| `Lexer\Cursor`, `Lexer\Slugger`, les règles | Par les lecteurs, chaque branche par une source écrite pour elle, et seuls |
| `Reader\UjoReader` | Un document valide, du JSON qui n'en est pas, un bloc refusé |
| `Reader\BBCode\BBCodeReader` | Chaque balise et extension et ses cas limites, les options et les attributs, les sources mal formées, les nouvelles lignes et les lignes vides, les liens et les images refusés, une imbrication plus profonde que ce que permet UJO, le HTML brut gardé et échappé, les métadonnées |
| `Reader\MediaWiki\MediaWikiReader`, `Reader\DokuWiki\DokuWikiReader`, `Reader\Creole\CreoleReader` | La syntaxe native de chaque wiki et les balises d'extension partagées, chaque source comparée au HTML que produit son document UJO ; marques, liens, modèles et conteneurs laissés ouverts ou qui se croisent, imbrication plus profonde que ce que permet UJO, HTML brut gardé et échappé, métadonnées, les chemins des pages et des médias |
| `Reader\Html\HtmlReader` | Chaque élément et les classes de `phil/ujo`, les styles d'un collage, les éléments laissés de côté, une imbrication plus profonde que ce que permet UJO et que ce qui est lu, les métadonnées d'une page |
| `WikiLinearityTest` | Des sources de 100 Ko écrites pour être lentes, pour le BBCode, chaque wiki et le HTML, chacune lue au huitième et à la moitié de sa taille, le temps processeur maintenu sous la croissance qu'atteindrait une lecture quadratique ; ces tests n'attribuent aucune couverture, pour que les tests de mutation ne les rejouent pas pour chaque mutant |
| `Adapter\Autolink` | Adresses changées en liens, échappement |
| `Adapter\DefaultCompiler`, `Adapter\PlainText` | Chaque format, le HTML brut selon le niveau, les lecteurs qu'il donne, les paragraphes et les sauts de ligne |
| `Reader\Text\TextReader` | Paragraphes, sauts de ligne, échappement et liens comme blocs UJO, le même texte que `PlainText` une fois rendu |
| `Adapter\SanitizerPurifier` | Ce que chaque niveau garde et retire, les schémas des liens et des images, les hôtes d'où viennent les images, une longue entrée |
| `Markup`, `MarkedUpText` | Compiler et assainir, avec un compilateur qui connaît les niveaux et un qui ne les connaît pas ; l'objet intégrable écrit et relu par Doctrine sur SQLite |

```sh
vendor/bin/phpunit packages/markup/tests
```

Les fichiers source sont maintenus à 100 % de mutants tués par Infection, lancé depuis la racine du monodépôt.

## Bancs d'essai

`BBCodeBench` lit des messages de forum, puis des sources écrites pour être lentes (balises imbriquées profondément,
balises fermantes sans ouvrante, crochets qui n'ouvrent aucune balise, éléments, lignes et cellules laissés ouverts,
titres et notes de bas de page par milliers), à 25, 50 et 100 Ko ; le temps double avec la taille. `ReadersBench` lit
un petit document dans chaque format, le même document répété jusqu'à 10 et 100 Ko, et 10 et 100 Ko de délimiteurs
laissés ouverts ; le temps est multiplié par dix de 10 à 100 Ko. Depuis la racine du monodépôt :

```sh
composer bench -- packages/markup/tests/Benchmark
```

En mode *worker*, `tests/Worker/worker.php` est une page que FrankenPHP sert depuis un seul processus d'une requête à
l'autre ; elle lit un document Markdown de 10 Ko et en fait du HTML assaini. Les services qu'elle construit avant sa
boucle servent chaque requête, comme dans le *worker* d'une application :

```sh
composer bench:worker phil/markup
```

## Fuzzing

Les cibles de fuzzing `markup-markdown`, `markup-bbcode`, `markup-creole`, `markup-dokuwiki`, `markup-mediawiki` et
`markup-html` donnent à chaque lecteur des entrées que personne n'a écrites, à partir des germes de
`tests/Fuzz/corpus/` : le lecteur d'un format écrit ne lance jamais d'exception, quoi qu'il lise. Depuis la racine du
monodépôt :

```sh
composer fuzz -- markup-markdown markup-html
```

## Tester du code qui utilise la bibliothèque

Comparez du HTML plutôt que de l'UJO quand ce qui compte est ce que montre une page :
`HtmlRenderer::default()->render($document)` donne une ligne par bloc. Les identifiants de blocs sont donnés dans
l'ordre de lecture, `b1`, `b2`…, si bien qu'un document lu deux fois à partir de la même source est identique.
