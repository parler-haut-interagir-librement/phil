<!-- phil-dev source-sha256=5fd5234f489105014ce52c01b7b8473b72e741f987067a716ab7b7ec194f3701 -->
# Formats

Chaque format atteint les mêmes fonctionnalités. Ce qu'un langage a nativement s'écrit à sa façon ; ce qui lui manque
s'écrit avec une extension de ce langage, dans son propre style. Chaque fonctionnalité aboutit au même bloc ou au même
élément en ligne UJO, quel que soit le format dans lequel elle a été écrite : un texte peut donc être converti d'un
format à l'autre par UJO.

## Les formats

| Format | `MarkupFormat` | Lu en UJO par | Compilé |
|---|---|---|---|
| Markdown | `markdown` | `Reader\Markdown\MarkdownReader` | UJO, rendu par `phil/ujo` |
| UJO | `ujo` | `Reader\UjoReader` | UJO, rendu par `phil/ujo` |
| BBCode | `bbcode` | `Reader\BBCode\BBCodeReader` | UJO, rendu par `phil/ujo` |
| MediaWiki | `mediawiki` | `Reader\MediaWiki\MediaWikiReader` | UJO, rendu par `phil/ujo` |
| DokuWiki | `dokuwiki` | `Reader\DokuWiki\DokuWikiReader` | UJO, rendu par `phil/ujo` |
| Creole | `creole` | `Reader\Creole\CreoleReader` | UJO, rendu par `phil/ujo` |
| HTML | `html` | `Reader\Html\HtmlReader` | tel qu'écrit, pour l'assainisseur |
| Texte brut | `text` | `Reader\Text\TextReader` | `Adapter\PlainText`, directement en HTML |

Le HTML est compilé tel qu'il est écrit, pour que ce qu'a écrit un rédacteur parvienne intact à l'assainisseur, et le
texte brut directement en paragraphes ; leurs lecteurs les convertissent en UJO quand un texte doit devenir des blocs.
Un module complémentaire apporte un format à lui sous un autre nom : voir [Points d'extension](extension-points.md).

## Correspondance

Une cellule nomme la syntaxe ; « — » marque une fonctionnalité que le format n'a pas. La colonne UJO est ce que devient
chaque syntaxe de la ligne. La colonne Markdown décrit le Markdown de PHIL, blocs `{{ }}` et pages compris. Les trois
wikis partagent leurs balises d'extension : MediaWiki et DokuWiki les écrivent `<note>`, Creole `<<note>>`, fermées par
`</note>` et `<</note>>` ; une cellule de la colonne DokuWiki ou Creole qui dit « la balise » désigne la balise de la
colonne MediaWiki, dans ses propres délimiteurs. La colonne HTML nomme les éléments, et les classes du HTML que produit
`phil/ujo`, que le lecteur HTML relit.

### Texte et éléments en ligne

| Fonctionnalité | UJO | Markdown | BBCode | MediaWiki | DokuWiki | Creole | HTML |
|---|---|---|---|---|---|---|---|
| Gras, italique | `<b>`, `<i>` | `**b**`, `*i*`, `__b__`, `_i_` | `[b]`, `[i]` | `'''b'''`, `''i''`, `'''''both'''''` | `**b**`, `//i//` | `**b**`, `//i//` | `<b>`, `<strong>`, `<i>`, `<em>`, `<cite>`, un style gras ou italique |
| Barré | `<s>` | `~s~`, `~~s~~` | `[s]` | `<s>`, `<del>`, `<strike>`, `{{s\|…}}` | `<del>` | `<<s>>…<</s>>` | `<s>`, `<del>`, `<strike>`, `text-decoration: line-through` |
| Souligné | `<u>` | — | `[u]` | `<u>`, `<ins>`, `{{u\|…}}` | `__u__` | `__u__` | `<u>`, `<ins>`, `text-decoration: underline` |
| Surlignage | `<mark>` | `==text==` | `[mark]`, `[highlight]` | `<mark>`, `{{Mark\|…}}`, `{{Highlight\|…}}` | `<mark>` | `<<mark>>…<</mark>>` | `<mark>` |
| Exposant, indice | `<sup>`, `<sub>` | — | `[sup]`, `[sub]` | `<sup>`, `<sub>` | `<sup>`, `<sub>` | `^^sup^^`, `,,sub,,` | `<sup>`, `<sub>` |
| Code en ligne | `<code>` | `` `code` `` | `[icode]` | `<code>`, `<tt>`, `{{code\|…}}` | `''code''` | `##code##`, `{{{text}}}` | `<code>`, `<kbd>`, `<samp>`, `<tt>` |
| Texte gardé tel qu'écrit | son texte | `\` avant un caractère | `[code]`, `[icode]` | `<nowiki>` | `<nowiki>`, `%%text%%` | `~` avant un caractère, `{{{text}}}` | — |
| Saut de ligne | `<br>` | deux espaces ou `\` à la fin d'une ligne | une nouvelle ligne | `<br>` | `\\` avant une espace ou la fin de la ligne | `\\` | `<br>` |
| Lien | `<a href>` | `[text](address)`, `[text][label]`, `[label]` | `[url]`, `[url=address]`, `[email]` | `[[Page\|text]]`, `[[Page#Section]]`, `[https://… text]` | `[[page\|text]]`, `[[namespace:page#section]]`, `[[https://…\|text]]` | `[[page\|text]]`, `[[https://…\|text]]` | `<a href>` |
| Lien automatique | `<a href>` | `<https://…>`, `<name@example.com>`, `https://…`, `www.…` | une adresse dans le texte | une adresse dans le texte | une adresse dans le texte, `<name@example.com>` | une adresse dans le texte | — |
| Espace insécable | U+00A0 | — | `[nbsp]` | `&nbsp;`, `{{nbsp}}`, `<nbsp>` | la balise | la balise | `&nbsp;` |
| Icône | `<i data-icon="set:name">` | — | `[icon=fas_fa-dragon]` | `{{Icon\|fas_fa-dragon}}`, `<icon fas_fa-dragon>` | la balise | la balise | `<i data-icon>` |
| Espace horizontale | `<span data-hspace>` | — | `[hspace=100px]` | `{{Hspace\|100px}}`, `<hspace 100px>` | la balise | la balise | `<span data-hspace>` |
| Couleur du texte | `<span class="text-color" data-color>` | — | `[color=red]` | `{{Color\|red\|text}}`, `<color red>` | la balise | la balise | `<span data-color>` |
| Divulgâcheur | `<span class="spoiler">` | — | `[spoiler]` | `{{Spoiler\|text}}`, `<spoiler>` | la balise | la balise | `<span class="spoiler">` |
| Variable | sa valeur | — | `[var=name]value[/var]`, `[var]name[/var]` | `{{#vardefine:name\|value}}`, `{{#var:name}}`, `<var name="value">`, `<var name>` | `<var name="value">`, `<var name>` | `<<var name="value">>`, `<<var name>>` | — |
| Note de bas de page | `<sup data-footnote>` et `footnote` | `[^label]`, `[^label]: text` | `[footnote]text[/footnote]` | `<ref>text</ref>`, `<ref name="a">`, `<ref name="a"/>`, `{{Footnote\|text}}` | `((text))` | `<<footnote>>text<</footnote>>` | `<sup data-footnote>` et son `<aside class="ujo-footnote">` |
| HTML brut | éléments en ligne, assainis | gardé à `all`, échappé en dessous | `[html]` comme bloc | `<html>`, gardé à `all`, échappé en dessous | la balise | la balise | — |

### Blocs

| Fonctionnalité | UJO | Markdown | BBCode | MediaWiki | DokuWiki | Creole | HTML |
|---|---|---|---|---|---|---|---|
| Paragraphe | `paragraph` | des lignes entre des lignes vides | du texte, une ligne vide entre deux | des lignes entre des lignes vides | pareil | pareil | `<p>`, le texte entre les blocs |
| Titre | `header`, son ajustement d'ancre | `#` à `######`, ou souligné par `===` ou `---` | `[h1]`…`[h6]` | `= … =` à `====== … ======` | `====== … ======` est le premier niveau, `== … ==` le cinquième | `= … =` à `====== … ======`, les signes fermants facultatifs | `<h1>`…`<h6>` |
| Ancre de titre | ajustement `anchor` | générée, ou `{#id}` | générée, ou `id=` | générée, ou `id` sur un `<div>` autour | générée, ou `id` sur une balise autour | générée, ou `id` sur une balise autour | générée, ou `id` |
| Liste, liste ordonnée | `list` | `-`, `+`, `*` ; `1.`, `1)` | `[list]`, `[*]` ; `[list=1]`, `[list=a]`, `[list=i]` | `*`, `#`, un signe de plus pour chaque niveau | `  *`, `  -`, deux espaces de plus pour chaque niveau | `*`, `#`, un signe de plus pour chaque niveau | `<ul>`, `<ol>`, `start`, `type` |
| Liste de tâches | `list`, style `checklist` | `- [ ]`, `- [x]` sur chaque élément | `[list=check]`, `[*=x]` | `[ ]`, `[x]` après le signe de chaque élément | pareil | pareil | une case à cocher, ou `data-checked`, dans chaque élément |
| Paragraphe en retrait | `paragraph`, son ajustement `indent` | — | — | `:`, `::`, un signe pour chaque niveau | — | `:`, `::` | — |
| Citation | `quote` | `>` | `[quote]` | `<blockquote>` | `>` | `>` | `<blockquote>` |
| Citation avec attribution | `quote`, sa légende | — | `[quote=author]` | `<quote author="…">`, `<blockquote author="…">` | la balise | la balise | `<figure>`, son `<blockquote>` et son `<figcaption>` |
| Bloc de code | `code`, son langage | clôturé par ```` ``` ```` ou `~~~`, ou indenté | `[code]`, `[code=language]` | des lignes qui commencent par une espace, `<pre>`, `<syntaxhighlight lang="…">`, `<source lang="…">`, `<code php>` | des lignes indentées de deux espaces, `<code php>`, `<file php>` | `{{{` et `}}}` sur des lignes à eux, `<<code php>>` | `<pre>`, son `<code class="language-…">` |
| Filet | `delimiter` | `***`, `---`, `___` | `[hr]` | `----` | `----` | `----` | `<hr>` |
| Tableau | `table` | les tableaux de GitHub | `[table]`, `[tr]`, `[th]`, `[td]` | `{\|`, `!`, `\|`, `\|-`, `\|}` | `^` en-têtes, `\|` cellules | `\|=` en-têtes, `\|` cellules | `<table>`, `<th>`, `<td>` |
| Image | `image`, son `alt`, sa légende, son ajustement de mise en page | `![alt](address "caption")`, seule ou dans un paragraphe | `[img]`, `[img=alt width=… wrap=left]` | `[[File:a.png\|left\|200px\|alt=…\|Caption]]` | `{{ a.png?200\|Caption}}`, placée selon ses espaces | `{{a.png\|alt}}` | `<img alt title>`, `<figure>` et son `<figcaption>` |
| Liste de définitions | `definitionList` | `Term` puis `: definition` | `[dl]`, `[dt]`, `[dd]` | `; Term : Definition`, `: Definition` | `  = Term : Definition`, `  : Definition` | `; Term : Definition`, `: Definition` | `<dl>`, `<dt>`, `<dd>` |
| Table des matières | `toc` | `[TOC]` | `[toc]`, `[toc=Title depth=4]` | `__TOC__`, `{{TOC\|title=…}}`, `<toc title="…" depth=4>` | `~~TOC~~`, la balise | la balise | `<nav class="ujo-toc">` |
| Préambule | `Reading::$metadata` | du YAML entre des lignes `---` | `[metadata]` YAML, `[style]` CSS | `<metadata>` YAML, `<style>` CSS | les balises | les balises | `<title>`, `<meta name="…" content="…">` |
| Bloc de HTML brut | `raw` | gardé à `all`, échappé en dessous | `[html]`, gardé à `all`, échappé en dessous | `<html>` sur des lignes à lui, gardé à `all`, échappé en dessous | la balise | la balise | — |
| Attributs | ajustements, ou un `container` | `{.class #id}` après un titre ou sur la ligne sous un bloc | `id=`, `class=`, `align=`, `width=`, `height=`, `wrap=` sur une balise de bloc | `id`, `class`, `align`, `width`, `height`, `wrap` sur une balise d'extension ; `<div id="…">` autour d'un bloc | les mêmes, sur la balise | les mêmes, sur la balise | `id`, `align`, `text-align`, les classes `ujo-align-…` et `ujo-wrap-…` |
| Note encadrée, texte à lire à voix haute | `note`, `descriptive` | `::: note Title`, `{{note …}}` | `[note=Title]`, `[descriptive]` | `<note title="…">`, `<descriptive>` | les balises | les balises | `<aside class="ujo-note">`, `ujo-descriptive`, leur `ujo-box__title` |
| Avertissement, alerte | `warning`, `alert` | `::: warning`, `::: info` et les autres types d'alerte | `[warning=Title]`, `[alert=info]` | `<warning title="…">`, `<alert info>` | les balises, et `<note warning>`, `<note important>`, `<note tip>` du module de notes | les balises | `<aside class="ujo-warning">`, `<div class="ujo-alert ujo-alert--info">` |
| Bloc repliable | `toggle` | — | `[spoiler=Title]` | `<spoiler title="…">`, `<toggle>` qui contient des blocs | les balises, et `<hidden title="…">` | les balises | `<details>`, son `<summary>` |
| Une classe de l'apparence | `container` | `::: {.class}`, `{{class …}}` | `[block=class]`, `class=` | `<div class="…">`, `<block class>` | `<WRAP class>`, les balises | les balises | `<div class="ujo-container …">` |

### Pages et livres de jeu

| Fonctionnalité | UJO | Markdown | BBCode | MediaWiki | DokuWiki | Creole | HTML |
|---|---|---|---|---|---|---|---|
| Saut de page, saut de colonne | `pageBreak`, `columnBreak` | `\page`, `\column` | `[pagebreak]`, `[columnbreak]` | `{{Page break}}`, `{{Column break}}`, `<pagebreak>`, `<columnbreak>` | `~~PAGEBREAK~~`, `~~COLUMNBREAK~~`, les balises | les balises | `ujo-page-break`, `ujo-column-break` |
| Style de la page suivante, couverture | `pageStyle` | `\page {class}` | `[pagebreak=frontCover]`, `[block=frontCover]` | `{{Page break\|frontCover}}`, `<pagebreak frontCover>`, `<block frontCover>` | `~~PAGEBREAK:frontCover~~`, les balises | les balises | `ujo-page-style`, son `data-variant` |
| Sur toute la largeur des colonnes | `wide` | `::: wide`, `{{wide …}}` | `[wide]`, l'indicateur `wide` | `<wide>`, l'indicateur `wide` | pareil | pareil | `ujo-wide` |
| Colonnes côte à côte | `columns` | — | `[columns][column]…[column]…[/columns]` | `<columns><column>…<column>…</columns>` | pareil, et `<newcolumn>` | pareil | `ujo-columns`, son `ujo-column` |
| Colonnes à écoulement | `columnFlow` | — | `[columns=3]` | `<columns 3>` | pareil | pareil | `ujo-column-flow--3` |
| Lignes vides | `spacer` | — | `[spacer=3]` | `{{Spacer\|3}}`, `<spacer 3>` | `~~SPACER:3~~`, la balise | la balise | `ujo-spacer`, son `data-lines` |
| Alignement | ajustement `alignment` | `{text-align:center}` sous un bloc | `[center]`, `[left]`, `[right]`, `[justify]`, `[align=…]`, `align=` | `<center>`, `<left>`, `<right>`, `<justify>`, `<align right>`, `align=` | pareil | pareil | `<center>`, `align`, `text-align` |
| Numéro de page | `pageNumber` | `{{pageNumber 1}}` | `[pagenumber]`, `[pagenumber=12]`, `[pagenumber=skip]`, `[pagenumber=reset]` | `{{Page number\|12}}`, `<pagenumber 12>`, `<pagenumber skip>`, `<pagenumber reset>` | `~~PAGENUMBER:12~~`, les balises | les balises | `ujo-page-number`, son `data-mode` |
| Pied de page courant | `runningFooter` | `{{footnote …}}` | `[footer]` | `<footer>` | la balise | la balise | `ujo-running-footer` |
| Filigrane, bannière | `watermark`, `banner` | `{{watermark …}}`, `{{banner …}}` | `[watermark]`, `[banner]` | `<watermark>`, `<banner>` | les balises | les balises | `ujo-watermark`, `ujo-banner` |
| Décoration | `decoration` | `{{watercolor1}}` à `{{watercolor99}}` | `[decoration=12 color=#rrggbb]` | `{{Decoration\|12\|color=#rrggbb}}`, `<decoration 12 color=#rrggbb kind=ornament>` | `~~DECORATION:12 color=#rrggbb~~`, la balise | la balise | `ujo-decoration--stain-12` |
| Bloc de statistiques | `statblock` | `{{monster …}}`, `{{statblock,spell …}}` | `[statblock]`, `[monster]`, `frame`, `wide`, `name=`, `subtitle=` | `<statblock name="…" subtitle="…" frame wide>`, `<monster>` | les balises | les balises | ses blocs |
| Table de classe | `table`, ajustement `variant` | `{{classTable …}}` | `[classtable frame decoration]` | `<classtable frame decoration>` | la balise | la balise | son tableau, sans la variante |
| Crédit d'illustration | `credit` | `{{artist …}}` | `[credit=Title]`, `[artist]` | `<credit title="…">`, `<artist>` | les balises | les balises | `ujo-credit`, son `ujo-credit__title` |

## Prévu

Les fonctionnalités ci-dessous font partie du Markdown de PHIL et de chaque autre format, et ne sont pas encore lues :
des attributs par défaut par type de bloc, les liens vers d'autres sites (`rel`, `target`, les domaines propres du
site), les mentions `@name` et `#ticket`, une adresse seule sur sa ligne comme contenu intégré, la ponctuation
typographique par `phil/typography`, des niveaux de titres rendus consécutifs, et un mode en ligne seulement.
[BBCode](bbcode.md), [Les wikis](wikis.md) et [HTML](html.md) détaillent leurs colonnes.
