<!-- phil-dev source-sha256=8f47438d5ffe707c788ee6584919b841513e7c55cff8a1e9636acec645c18c72 -->
# Les wikis

`Reader\MediaWiki\MediaWikiReader`, `Reader\DokuWiki\DokuWikiReader` et `Reader\Creole\CreoleReader` lisent la syntaxe
de trois wikis et en font un document UJO, dont `phil/ujo` produit le rendu HTML. Chacun lit la syntaxe native de son
wiki (titres, emphase, liens vers des pages et vers d'autres sites, listes, tableaux, texte préformaté, texte gardé tel
qu'écrit, images, filets) et les extensions que partagent tous les wikis de PHIL, écrites comme le wiki écrit les
siennes : des balises dans MediaWiki et DokuWiki, `<note>`, des espaces réservés dans Creole, `<<note>>` ; des modèles
et des mots magiques dans MediaWiki, `{{TOC}}`, `__TOC__` ; des macros dans DokuWiki, `~~TOC~~`. Ensemble, ils donnent
à chaque wiki toutes les fonctionnalités du lecteur Markdown. [Formats](formats.md) place chaque syntaxe à côté de
celles des autres formats.

```php
$reading = new MediaWikiReader()->read("== Rules ==\nRoll '''two''' dice.<ref>Six-sided.</ref>");

$reading->document->blocks[0];   // a `header` block, level 2, its anchor `rules`
$reading->document->blocks[2];   // the `footnote` block, `Six-sided.`
```

`new MediaWikiReader(true)` garde le HTML brut ; `DefaultCompiler` le demande au seul niveau `all`. Un deuxième argument
remplace le normaliseur de `phil/ujo` qui vérifie le document ; un troisième, un `Reader\Wiki\WikiLinks`, dit où se
trouvent les pages et les fichiers.

## Pages et fichiers

Un lien vers une page, `[[Main Page#History]]`, mène au chemin de la page : ses espaces deviennent des traits de
soulignement, ses autres caractères non sûrs sont encodés, et sa section devient une ancre produite comme les lecteurs
produisent les ancres des titres : `/Main_Page#history`. Un fichier, `[[File:map.png]]` ou `{{map.png}}`, mène au
chemin des médias. Une adresse Web est gardée telle qu'écrite.

```php
new MediaWikiReader(false, null, new WikiLinks(pages: '/wiki/', media: 'https://cdn.example.com/'));
```

Les deux chemins commencent par `/` par défaut, pour qu'un nom qui contient `:` (un espace de noms, `Help:Contents`) ne
soit jamais lu comme un schéma. DokuWiki écrit ses noms de pages en minuscules, comme le fait DokuWiki.

## Les balises d'extension

Une balise s'écrit `<name>`, `<name flag key="value">` ou `<name/>` dans MediaWiki et DokuWiki, `<<name …>>` dans
Creole, son nom en majuscules ou en minuscules, et se ferme par `</name>` ou `<</name>>`. Une valeur qui contient des
espaces se met entre guillemets.

### En ligne

| Balise | UJO |
|---|---|
| `<b>`, `<strong>`, `<i>`, `<em>`, `<u>`, `<ins>`, `<s>`, `<del>`, `<strike>`, `<sup>`, `<sub>`, `<mark>`, `<tt>`, `<kbd>` | `<b>`, `<i>`, `<u>`, `<s>`, `<sup>`, `<sub>`, `<mark>`, `<code>` |
| `<span>`, `<small>`, `<big>` | le texte seul |
| `<code>…</code>` fermé sur sa ligne, `<nowiki>…</nowiki>` | `<code>`, ou le texte, tel qu'écrit |
| `<br>`, `<br/>` | `<br>` |
| `<footnote>…</footnote>`, `<ref>…</ref>`, `<ref name="a">…</ref>`, `<ref name="a"/>` | `<sup data-footnote>` là où elle est écrite, et un bloc `footnote` à la fin ; une note nommée est référencée de nouveau par son nom |
| `<spoiler>…</spoiler>` | `<span class="spoiler">` |
| `<color red>…</color>`, `<color color="#ff0000">` | `<span class="text-color" data-color>` ; toute autre valeur garde le texte seul |
| `<icon fas_fa-dragon>`, `<hspace 10px>`, `<nbsp>` | `<i data-icon>`, `<span data-hspace>`, U+00A0 |
| `<var name="value">`, `<var name>` | définit une variable, puis écrit sa valeur ; une variable pas encore définie garde la balise telle qu'écrite |
| `<html>…</html>` | son HTML gardé à `all`, échappé en dessous |
| `<references/>` | rien : les notes de bas de page sont ajoutées à la fin |

Une balise inconnue, ou laissée ouverte, est du texte. Une balise qui contient une balise de son propre nom se ferme à
la première balise fermante.

### Blocs

Un conteneur s'ouvre par sa balise au début d'une ligne, et contient les blocs jusqu'à sa balise fermante (sur la même
ligne ou sur une ligne suivante), ou jusqu'à la balise fermante d'un conteneur qui l'entoure, ou jusqu'à la fin.
`<note>a</note>` est une note qui contient un paragraphe.

| Balise | UJO |
|---|---|
| `<note title="…">`, `<descriptive title="…">` | `note`, `descriptive`, le titre donné ou celui du titre de section par lequel ils commencent |
| `<warning title="…">`, `<alert info>` et les autres types d'alerte | `warning`, `alert` |
| `<note warning>`, `<note important>`, `<note tip>` | `warning`, `alert` du type `danger`, du type `success` : le module de notes de DokuWiki |
| `<quote author="…">`, `<blockquote author="…">` | `quote`, l'auteur comme légende |
| `<spoiler title="…">`, `<toggle>`, `<hidden title="…">` | `toggle`, fermé |
| `<wide>`, l'indicateur `wide` sur un encadré | `wide` |
| `<block class>`, `<div class="…">`, `<WRAP class>` | `container`, pour une apparence qui déclare les classes |
| `<columns>`, `<column>`, `<newcolumn>` | `columns`, six au plus, les blocs des autres joints à la sixième ; sans colonne, `columnFlow`, `<columns 3>` |
| `<statblock name="…" subtitle="…" frame wide>`, `<statblock spell>`, `<monster>` | `statblock`, lu comme le lecteur Markdown lit une créature |
| `<credit title="…">`, `<artist>` | `credit` |
| `<classtable frame decoration>` | l'ajustement `variant` sur les tableaux à l'intérieur |
| `<center>`, `<left>`, `<right>`, `<justify>`, `<align right>` | l'ajustement `alignment` sur chaque bloc à l'intérieur |
| `<footer>`, `<watermark>`, `<banner>` | `runningFooter`, `watermark`, `banner` |

Chaque conteneur prend les attributs `id` (l'ajustement `anchor` du bloc qu'il produit), `class` (un `container`
autour), `align`, `width`, `height` et `wrap="left"` ou `wrap="right"`. Les conteneurs s'imbriquent aussi profondément
que le permet UJO : la balise d'un conteneur plus profond est laissée de côté, ses blocs lus comme ceux du conteneur
qui l'entoure. Une balise fermante qui ne ferme rien d'ouvert est laissée de côté.

| Balise qui ne contient rien | UJO |
|---|---|
| `<toc title="…" depth=4 wide>` | `toc` |
| `<pagebreak>`, `<pagebreak frontCover>`, `<columnbreak>` | `pageBreak`, puis `pageStyle` pour les classes ou la couverture données ; `columnBreak` |
| `<spacer 3>`, `<pagenumber 12>`, `<pagenumber skip>`, `<pagenumber reset>`, `<decoration 12 color=#rrggbb kind=ornament>` | `spacer`, `pageNumber`, `decoration` |

| Balise dont le contenu est gardé tel qu'écrit | UJO |
|---|---|
| `<code php>`, `<file php name.js>`, `<syntaxhighlight lang="php">`, `<source lang="php">` | `code`, son langage |
| `<pre>`, `<nowiki>` sur des lignes à eux, `<code>` sans langage et non fermé sur sa ligne | `code` |
| `<html>` sur des lignes à lui | `raw` à `all`, son texte échappé en dessous |
| `<metadata>` YAML, `<style>` CSS | `Reading::$metadata`, le style sous `style` |

## MediaWiki

| Syntaxe | UJO |
|---|---|
| `= … =` à `====== … ======` | `header`, son niveau étant le plus petit des nombres de signes qui l'ouvrent et le ferment |
| `''italic''`, `'''bold'''`, `'''''both'''''` | `<i>`, `<b>` ; une quatrième apostrophe et celles au-delà de cinq sont des apostrophes ; une marque laissée ouverte est du texte |
| `[[Page]]`, `[[Page\|text]]`, `[[Page#Section\|text]]`, `[[#Section]]`, `[[dog]]s` | un lien vers la page, les lettres qui suivent faisant partie de son texte |
| `[[Category:…]]` | rien ; `[[:Category:…]]` est un lien |
| `[https://… text]`, `[https://…]`, `[mailto:…]`, une adresse dans le texte | un lien |
| `[[File:a.png\|thumb\|left\|200px\|alt=…\|Caption]]`, `[[Image:…]]` | `image` : `left` et `right` habillent le texte autour, `center` la centre, `200px`, `200x100px` et `x100px` la dimensionnent, `class=` ajoute des classes, `alt=` donne son texte de remplacement, la dernière autre option est sa légende |
| `*`, `#`, `**`, `#*` ; `*:` | `list`, un signe de plus pour chaque niveau ; une ligne qui termine ses signes par `:` continue l'élément précédent |
| `; Term : Definition`, `: Definition` | `definitionList` |
| `:`, `::` pas après un terme | `paragraph`, son ajustement `indent` |
| `{\|` … `\|}`, `\|-`, `! a !! b`, `\| a \|\| b`, `\| style="…" \| a` | `table` ; une ligne de texte continue la cellule ou la légende précédente ; la légende `\|+` donne sa `caption` ; `colspan`, `rowspan`, `align` et le `text-align` de `style` gardés dans la forme riche |
| une ligne qui commence par une espace | `code` |
| `----` | `delimiter` |
| `&nbsp;`, `&amp;`, `&#233;` | leurs caractères |
| `__TOC__`, `__FORCETOC__` ; `__NOTOC__` et les autres mots magiques | `toc` ; rien |
| `<!-- … -->` | rien |
| `{{TOC\|title=…}}`, `{{Page break\|frontCover}}`, `{{Column break}}`, `{{Spacer\|3}}`, `{{Page number\|12}}`, `{{Decoration\|12\|color=#rrggbb}}` seuls sur une ligne | les blocs des balises du même nom |
| `{{Footnote\|…}}`, `{{Spoiler\|…}}`, `{{Mark\|…}}`, `{{Color\|red\|…}}`, `{{Icon\|…}}`, `{{Hspace\|…}}`, `{{nbsp}}`, `{{u\|…}}`, `{{s\|…}}`, `{{sup\|…}}`, `{{sub\|…}}`, `{{code\|…}}` | les éléments en ligne des balises du même nom |
| `{{#vardefine:name\|value}}`, `{{#var:name}}`, `{{var\|name}}` | une variable définie, sa valeur |

Un modèle se nomme en majuscules ou en minuscules, ses espaces et ses traits de soulignement laissés de côté :
`{{Page break}}` est `{{pagebreak}}`. Un modèle inconnu est du texte.

## DokuWiki

| Syntaxe | UJO |
|---|---|
| `====== … ======` à `== … ==` | `header` : six signes donnent le premier niveau, deux le cinquième |
| `**bold**`, `//italic//`, `__underline__`, `''monospace''` | `<b>`, `<i>`, `<u>`, `<code>` |
| `[[page]]`, `[[namespace:page#section\|text]]`, `[[https://…\|text]]`, `[[name@example.com]]` | un lien ; un lien vers un autre wiki, `[[wp>Name]]`, garde son texte seul |
| `<name@example.com>`, une adresse dans le texte | un lien |
| `((text))` | une note de bas de page |
| `%%text%%` | le texte, tel qu'écrit |
| `\\` avant une espace ou la fin de la ligne | `<br>` |
| `{{a.png}}`, `{{ a.png}}`, `{{a.png }}`, `{{ a.png }}`, `{{a.png?200x100\|Caption}}` | `image`, le texte comme légende, à droite, à gauche ou centrée selon ses espaces, dimensionnée ; un fichier qui n'est pas une image donne un lien vers lui |
| `  * item`, `  - item`, deux espaces ou une tabulation de plus pour chaque niveau | `list` |
| `  = Term : Definition`, `  : Definition` | `definitionList` |
| des lignes indentées de deux espaces qui ne sont pas des éléments | `code` |
| `^ heading ^`, `\| cell \|`, `:::`, `\|\|` | `table`, une première ligne faite seulement d'en-têtes donnant ses en-têtes, un en-tête ailleurs une cellule d'en-tête ; `:::` élargit d'une ligne la cellule au-dessus, une cellule vide élargit d'une colonne la cellule précédente ; deux espaces avant le texte l'alignent à droite, après lui à gauche, des deux côtés au centre |
| `>`, `>>` | `quote`, chaque ligne à part |
| `----` | `delimiter` |
| `~~TOC~~`, `~~PAGEBREAK:frontCover~~`, `~~COLUMNBREAK~~`, `~~SPACER:3~~`, `~~PAGENUMBER:12~~`, `~~DECORATION:12 color=#rrggbb~~` seuls sur une ligne | les blocs des balises du même nom |
| `~~NOTOC~~`, `~~NOCACHE~~` | rien |

## Creole

| Syntaxe | UJO |
|---|---|
| `= … =` à `====== … ======` | `header`, son niveau étant le nombre de signes qui l'ouvrent ; ceux qui le ferment sont facultatifs |
| `**bold**`, `//italic//` ; `##monospace##`, `^^superscript^^`, `,,subscript,,`, `__underline__` | `<b>`, `<i>`, `<code>`, `<sup>`, `<sub>`, `<u>` ; `//` dans une adresse fait partie de l'adresse |
| `[[page]]`, `[[page\|text]]`, `[[https://…\|text]]`, `[[page\|{{image.png}}]]` | un lien |
| `{{image.png\|text}}` | `image`, le texte comme texte de remplacement : Creole n'a pas de légende |
| `{{{text}}}` | `<code>`, le texte tel qu'écrit ; les accolades qui le ferment sont les trois dernières de leur suite |
| `{{{` et `}}}` sur des lignes à eux | `code` ; une ligne ` }}}`, indentée, est du texte |
| `\\` | `<br>` |
| `~` avant un caractère | le caractère, comme texte |
| `*`, `#`, `**`, `#*` | `list` ; une liste commence par un seul signe, pour qu'un paragraphe puisse commencer par du gras |
| `\|= heading \|`, `\| cell \|` | `table` |
| `; Term : Definition`, `: Definition` ; `:`, `::` | `definitionList` ; `paragraph`, son ajustement `indent` |
| `>` | `quote` |
| `----` | `delimiter` |
| `<<<…>>>` | rien : un espace réservé pour le wiki qui l'a écrit |

## Texte mal formé

Tout texte est un document, et les lecteurs ne lancent jamais d'exception. Une marque d'emphase ferme l'élément de son
genre encore ouvert, et ceux ouverts à l'intérieur et laissés ouverts sont du texte, pour que le HTML soit toujours bien
imbriqué ; une marque jamais fermée est du texte. Un lien, une image ou un modèle se lit dans les 4 Ko et sur une
seule ligne ; s'il n'y est pas fermé, c'est du texte. Un conteneur jamais fermé contient le reste du texte.

## Temps linéaire

Chaque ligne est lue une seule fois, les balises des conteneurs ayant d'abord été placées sur des lignes à elles par
une passe. Un délimiteur absent à partir d'une position est absent à partir de toutes les positions suivantes, ce dont
se souvient le lecteur en ligne ; le contenu est lu sur huit niveaux au plus, plus profond comme du texte ; un lien,
une image ou un modèle est cherché dans les 4 Ko. Les conteneurs ouverts sont comptés par nom, et une ligne de tableau
garde soixante-quatre cellules, le texte des autres joint à la dernière. Des sources de 100 Ko écrites pour être lentes
se lisent chacune en une fraction de seconde.
