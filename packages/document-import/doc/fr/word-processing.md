<!-- phil-dev source-sha256=5cbe273f36cbe1ab628d30c02e18c15f05bc55d29fb06b1e84471ab4fc35430b -->
# Documents de traitement de texte

Cette bibliothèque lit le DOCX, l'ODT et le RTF, construction par construction. Ce que devient une construction est le
même dans tous les formats. Les styles de paragraphe passent d'abord par la [table des styles](styles.md) de
l'application.

## Blocs

| Construction | DOCX | ODT | UJO |
|---|---|---|---|
| Paragraphe | `w:p` | `text:p` | `paragraph`, avec le réglage `alignment` quand il est centré, aligné à droite ou justifié |
| Titre | Style nommé `heading 1` à `heading 9`, ou doté d'un niveau de plan (0 à 8) le long de sa chaîne `basedOn` | `text:h` et son niveau de plan | `header`, les niveaux au-delà de 6 écrits comme 6 |
| Intitulé | Style nommé `Title` | Style de paragraphe `Title` | `header` de niveau 1 |
| Citation | Styles `Quote`, `Intense Quote`, `Block Text` | Styles de paragraphe `Quotations`, `Quote` | `quote` |
| Code | Styles `HTML Preformatted`, `Source Code`, `Code` ; les paragraphes consécutifs se joignent | Styles de paragraphe `Preformatted Text`, `Source Text` | `code`, un bloc par suite de lignes |
| Liste | `w:numPr` du paragraphe ou de son style, le niveau 0 de la numérotation donnant la sorte | `text:list` et son style de liste | `list`, imbriquée, `ordered` ou `unordered`, son compteur et son premier numéro |
| Tableau | `w:tbl`, le `w:tblHeader` de la première ligne en faisant des en-têtes | `table:table`, `table:table-header-rows` en faisant des en-têtes | `table`, chaque ligne aussi large que la plus large |
| Note de bas de page, note de fin | `w:footnoteReference`, `w:endnoteReference` | `text:note` et sa classe | Une référence `<sup data-footnote>` dans le texte et un bloc `footnote` à la fin, `fn-1`… et `en-1`… |
| Table des matières | Un champ `TOC` ou le contrôle de table des matières de Word | `text:table-of-content` et son titre | `toc` ; les entrées sont laissées de côté, le moteur de rendu les construit |
| Saut de page | `w:br w:type="page"` | Un style de paragraphe qui saute avant | `pageBreak` |
| Image | `a:blip` de DrawingML ou `v:imagedata` de VML, sa description ou son titre | `draw:frame` avec `draw:image`, son `svg:desc` ou son `svg:title` | `image`, son `alt` étant le texte de remplacement, coupé à 1000 caractères |
| Section, contrôle de contenu | `w:sdt`, `w:customXml`, révisions insérées | `text:section` | Leur contenu |

Une image coupe son paragraphe : le texte qui la précède, le bloc d'image et le texte qui la suit se succèdent. Une
cellule de tableau, un élément de liste d'un ODT et une note ne contiennent que du texte : une image rencontrée là
devient un bloc d'image après le tableau, la liste, ou le paragraphe qui renvoie à la note.

## Texte

| Construction | DOCX | ODT | HTML en ligne |
|---|---|---|---|
| Gras | `w:b` | `fo:font-weight` bold, ou 600 et plus | `<b>` |
| Italique | `w:i` | `fo:font-style` italic ou oblique | `<i>` |
| Souligné | `w:u` autre que `none` | `style:text-underline-style` autre que `none` | `<u class="cdx-underline">` |
| Barré | `w:strike`, `w:dstrike` | `style:text-line-through-style` autre que `none` | `<s class="cdx-strikethrough">` |
| Surlignage | `w:highlight` autre que `none` | `fo:background-color` autre que `transparent` | `<mark class="cdx-marker">` |
| Code en ligne | Styles de caractères `HTML Code`, `Verbatim Char`, `Source Code Char`, `Code Char` | Styles de texte `Source Text`, `Teletype`, `Example` | `<code class="inline-code">` |
| Exposants | `w:vertAlign` | `style:text-position` | `<sup>`, `<sub>` |
| Lien | `w:hyperlink` vers une relation externe, champs `HYPERLINK` | `text:a` | `<a href>` |
| Saut de ligne | `w:br`, `w:cr` | `text:line-break` | `<br>` |
| Tabulation, espaces | `w:tab` comme une espace | `text:tab` comme une espace, `text:s` comme ses espaces | Texte |

La mise en forme vient de la plage et de son style de caractères, avec les styles sur lesquels il est fondé (DOCX), ou
du style de texte et de ses parents, et du style automatique du paragraphe, c'est-à-dire sa mise en forme directe
(ODT). La mise en forme qu'un style de paragraphe donne à tout son texte, comme le gras d'un titre, n'est pas répétée
sur le texte. Quand deux liens contiennent le même texte, le plus intérieur l'emporte. Les instructions des champs ne
sont pas du texte ; leurs résultats en sont.

## Laissé de côté

Chacun de ces éléments est signalé, comme le décrit [le rapport](report.md) : les commentaires et les annotations ; les
suppressions suivies (le document est lu comme si chaque modification était acceptée) ; les en-têtes et pieds de page
qui contiennent du texte ; les zones de texte ; les formes, graphiques et diagrammes ; les équations ; les objets
incorporés ; les images manquantes, liées à un fichier, ou dans un format qu'un navigateur n'affiche pas (EMF, WMF, TIFF) ; les tableaux dans des cellules, gardés comme leur texte ; les cellules fusionnées,
scindées ; les liens vers des endroits du document, qui gardent leur texte ; le texte masqué ; les couleurs, polices et
tailles ; les caractères des polices de symboles ; les macros.

Les signets, les marques de vérification linguistique, les propriétés de section et les sauts de page automatiques sont
laissés de côté sans être signalés : ils ne portent rien que voit un lecteur.

## Office Open XML strict

Un DOCX enregistré en *Strict Open XML* utilise les espaces de noms d'ISO/IEC 29500 strict
(`http://purl.oclc.org/ooxml/…`) au lieu des espaces de noms transitionnels. La bibliothèque lit ses parties en prenant
ces espaces de noms pour leurs formes transitionnelles, si bien qu'un document strict donne les mêmes blocs qu'un
document transitionnel.

## OpenDocument plat

Un texte OpenDocument plat (`.fodt`) est un seul fichier XML : son `office:document` contient les métadonnées, les
styles, les styles automatiques, les pages maîtresses, les scripts et le corps qu'un ODT empaqueté répartit sur
`meta.xml`, `styles.xml` et `content.xml`, et ses images sont incorporées en Base64. Il est lu par le même lecteur,
construction par construction, qu'un ODT empaqueté ; ses scripts sont signalés comme des macros. Le fichier est analysé
comme une entrée d'archive (aucun réseau, aucun type de document) et n'est pas plus grand que `Limits::$entryBytes`.

## RTF

Le Rich Text Format est lu jeton par jeton : ses groupes et leur mise en forme des caractères, ses mots et symboles de
contrôle, ses destinations.

| Construction | RTF | UJO |
|---|---|---|
| Paragraphe | `\par`, `\sect` ; `\pard` remet ses propriétés à zéro | `paragraph`, aligné par `\qc`, `\qr`, `\qj` |
| Titre, citation, code | `\sN` et les noms de la feuille de styles, comme dans un DOCX ; `\outlinelevelN` du paragraphe ou de son style | `header`, `quote`, `code` |
| Liste | `\lsN` et `\ilvlN` ; le marqueur `\listtext` ou `\pntext` distinguant un élément numéroté d'une puce | `list`, imbriquée |
| Tableau | paragraphes `\intbl`, `\cell`, `\row` ; `\trhdr` sur la première ligne en faisant des en-têtes | `table` |
| Note de bas de page, note de fin | `\footnote`, `\ftnalt` pour une note de fin | Une référence dans le texte et un bloc `footnote` à la fin |
| Champ | `HYPERLINK "address"` liant son résultat ; `TOC` | Un lien ; `toc` |
| Saut de page | `\page` | `pageBreak` |
| Image | `\pict` avec `\pngblip` ou `\jpegblip`, hexadécimal ou `\binN` ; son `wzDescription` | `image` |
| Texte | `\b`, `\i`, `\ul…`, `\strike`, `\striked1`, `\highlightN`, `\super`, `\sub` ; `\uN` avec ses caractères de repli, `\'hh` dans la page de codes de `\ansicpgN`, `\mac` ou `\pc` ; `\line`, `\tab`, `\~`, `\-`, `\_` et les guillemets et tirets nommés | HTML en ligne |
| Informations | `\info` : `\title`, `\author`, `\subject`, `\keywords`, `\doccomm` | `title`, `creator`, `subject`, `keywords`, `description` |

Les polices, les tables de couleurs, les tables de listes, les tables de révisions, les thèmes et les autres
destinations qui ne contiennent pas de texte sont sautés, tout comme une destination qui commence par `\*` et que le
lecteur ne connaît pas. Les en-têtes et pieds de page qui contiennent du texte, les commentaires, les objets
incorporés, les formes et zones de texte, le texte masqué (`\v`), les couleurs, les tableaux imbriqués, les liens vers
des signets, et les images autres que PNG et JPEG (EMF, WMF, bitmaps) sont signalés. Les pages de codes lues sont
celles que connaît `mbstring` (1251, 1252, 1254, 850, 866 et les pages d'Asie de l'Est) et Mac Roman ; toute autre est
lue comme Windows-1252. Les groupes ne s'imbriquent pas plus profondément que `Limits::$depth`, les destinations qui ne
sont pas du texte ne contiennent pas plus de deux fois `Limits::$entryBytes`, et le fichier n'est pas plus grand que
`Limits::$totalBytes`. Les groupes non fermés et les accolades isolées sont tolérés.

## Documents Word 97-2003

Un fichier `.doc` de Word 97 à 2003 est un fichier composé d'enregistrements binaires, pas du XML. La bibliothèque ne le
lit pas : un fichier nommé `.doc` ou `.dot`, et tout fichier dont les octets commencent par la signature d'un fichier
composé, est refusé par `UnsupportedFormat`, avec un message qui demande un DOCX, un ODT ou un RTF. La même signature
marque un document Office protégé par un mot de passe, qui est refusé de la même façon.
