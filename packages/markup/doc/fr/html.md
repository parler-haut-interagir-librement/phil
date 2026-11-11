<!-- phil-dev source-sha256=1168fa1265ced108ce283ee13a3b0c54650e6e91eb4dc4e042786f79faa071f8 -->
# HTML

`Reader\Html\HtmlReader` lit du HTML et en fait un document UJO : le HTML que produit un éditeur par blocs (celui de
`phil/ujo`, dont les classes `ujo-*` sont relues en les blocs d'où elles viennent), le HTML qu'apporte un collage depuis
un traitement de texte ou un autre site, et le HTML de textes plus anciens. `DefaultCompiler` compile le HTML tel qu'il
est écrit, pour l'assainisseur ; c'est le lecteur qui le convertit en blocs, quand un texte est publié en UJO ou ouvert
dans un éditeur par blocs. [Formats](formats.md) place chaque élément à côté de la syntaxe des autres formats.

```php
$reading = new HtmlReader()->read('<h2>Rules</h2><p>Roll <strong>two</strong> dice.</p>');

$reading->document->blocks[0];   // a `header` block, level 2, its anchor `rules`
$reading->document->blocks[1];   // a `paragraph` block, `Roll <b>two</b> dice.`
```

La source est analysée par l'analyseur HTML5 de PHP, `Dom\HTMLDocument`, comme un navigateur l'analyse : les balises
laissées ouvertes sont fermées, celles qui sont mal placées sont déplacées, les entités sont lues. Tout texte est un
document ; le lecteur ne lance jamais d'exception. Le constructeur prend, dans l'ordre, le normaliseur de `phil/ujo` qui
vérifie le document, les `EmbedServices` auxquels un cadre est comparé, l'`ExtractedElementStore` qui stocke les
dessins en ligne, et le `SvgSanitizer` qui les assainit.

## Blocs

| HTML | UJO |
|---|---|
| `<p>`, `<address>`, le texte entre les blocs | `paragraph` |
| `<h1>`…`<h6>` | `header`, son ancre étant l'`id`, ou générée |
| `<ul>`, `<ol>`, `<menu>`, `<li>` | `list` ; `start` et `type` (`a`, `A`, `i`, `I`) gardés ; un autre élément que contient la liste est aussi un élément ; une liste dans un élément donne ses éléments imbriqués, les autres blocs sont repliés dans son contenu |
| une case à cocher, ou `data-checked`, dans chaque élément | `list`, style `checklist` |
| `<blockquote>` | `quote` |
| `<figure>` qui contient `<img>` et `<figcaption>` | `image`, l'`alt` de l'image comme texte de remplacement, le `<figcaption>` comme légende, la mise en page de la figure comme la sienne |
| `<figure>` qui contient `<blockquote>` et `<figcaption>` | `quote`, la légende comme attribution |
| `<pre>`, son `<code class="language-php">` | `code`, son langage |
| `<hr>` | `delimiter` |
| `<table>`, ses `<thead>`, `<tbody>`, `<tfoot>`, `<tr>`, `<th>`, `<td>` | `table`, ses lignes allongées jusqu'à la plus longue, fusions comprises ; une première ligne faite seulement de `<th>` donne ses en-têtes ; `<caption>`, `colspan`, `rowspan`, un `<th>` ailleurs et un alignement (`align`, `text-align`, `ujo-align-…`) en font la forme riche ; une liste dans une cellule devient des lignes, chaque élément après sa puce ou son numéro |
| `<iframe>` sur un hôte d'un service de `EmbedServices` | `embed`, ses `title`, `width` et `height` gardés ; tout autre cadre est un lien vers sa source |
| `<video>`, `<audio>` | `video`, `audio` : leur `src` et leurs `<source>` dans l'ordre, `<track>` avec `kind`, `srclang`, `label` et `default`, `poster`, `controls`, `loop`, `muted`, la taille et `title` ; rien sans source |
| `<math display="block">` | `math`, son MathML, et le LaTeX de son annotation `application/x-tex` |
| `<p>` qui ne contient que `<a download>`, ou un lien vers un fichier (`.pdf`, `.epub`, `.zip`, `.odt`, `.docx`, `.csv`…) | `attaches` : son adresse, le nom et l'extension du fichier, le texte du lien comme titre |
| `lang`, `dir` sur un bloc | l'ajustement `language`, quand la langue diffère de celle qui l'entoure |
| `data-*` sur un bloc | l'ajustement `data`, sauf pour ceux que le lecteur lit lui-même ou qu'écrit `phil/ujo` |
| un commentaire | l'ajustement `note` du bloc suivant, ou du dernier de son conteneur |
| `<dl>`, `<dt>`, `<dd>` | `definitionList` |
| `<details>`, `<summary>` | `toggle`, ouvert quand les détails le sont |
| `<center>`, `align="…"`, `style="text-align:…"` | l'ajustement `alignment` |
| `<div>`, `<section>`, `<article>`, `<main>`, `<header>`, `<footer>`, `<nav>`, `<aside>`, et un élément en ligne qui contient des blocs | leurs blocs |
| `<img>` dans un paragraphe | `image`, le paragraphe coupé autour ; son `alt` comme texte de remplacement, son `title` comme légende, `width`, `height` et `align` comme mise en page ; dans un lien, son texte de remplacement, ou sa légende à défaut |

## Le HTML de `phil/ujo`

Les blocs que produit `phil/ujo` portent la classe de leur type, que le lecteur relit :

| Classe | UJO |
|---|---|
| `ujo-note`, `ujo-descriptive`, leur `ujo-box__title` | `note`, `descriptive` |
| `ujo-warning`, ses `ujo-warning__title` et `ujo-warning__message` | `warning` |
| `ujo-alert`, `ujo-alert--danger` | `alert`, son type |
| `ujo-wide`, `ujo-container` et ses autres classes | `wide`, `container` |
| `ujo-columns` et ses `ujo-column`, `ujo-column-flow--3` | `columns`, `columnFlow` |
| `ujo-page-break`, `ujo-column-break`, `ujo-page-style` et son `data-variant` | `pageBreak`, `columnBreak`, `pageStyle` |
| `ujo-spacer` et son `data-lines`, `ujo-page-number` et son `data-mode` | `spacer`, `pageNumber` |
| `ujo-decoration--ornament-12` et sa couleur | `decoration` |
| `ujo-running-footer`, `ujo-watermark`, `ujo-banner` | `runningFooter`, `watermark`, `banner` |
| `ujo-toc` et son `ujo-toc__title` | `toc`, générée de nouveau à partir des titres |
| `ujo-credit` et son `ujo-credit__title` | `credit` |
| `<sup data-footnote="fn-1">` et l'`<aside id="footnote-fn-1">` qu'il nomme | une note de bas de page, numérotée de nouveau dans l'ordre des références |
| `ujo-align-…`, `ujo-wrap-left`, `ujo-wrap-right` | l'ajustement `alignment`, l'habillage d'une image |
| `ujo-embed`, `ujo-video`, `ujo-audio` et leur `ujo-print-fallback` | `embed`, `video`, `audio`, la page, l'affiche, le titre et le code QR de leur solution de repli pour le papier relus |

Un bloc de statistiques se lit comme ses blocs, une table de classe comme son tableau.

## En ligne

| HTML | UJO |
|---|---|
| `<b>`, `<strong>` ; `<i>`, `<em>`, `<cite>`, `<dfn>`, `<var>` | `<b>` ; `<i>` |
| `<u>`, `<ins>` ; `<s>`, `<del>`, `<strike>` ; `<mark>` ; `<sup>`, `<sub>` | `<u>` ; `<s>` ; `<mark>` ; `<sup>`, `<sub>` |
| `<code>`, `<kbd>`, `<samp>`, `<tt>` | `<code>` |
| `<a href>` vers une adresse d'un schéma que permet `phil/ujo` (le Web, `mailto`, `tel`, `sms`, `geo`, les messageries) ou un chemin du site | `<a href>` ; toute autre adresse garde le texte seul |
| `<a href="#…">` | `<a href>` quand un élément de la page a cet `id` ; son texte seul sinon |
| `<abbr title>`, `<acronym title>` | `<abbr title>` |
| `lang`, `dir` ; `<bdi>`, `<bdo dir>` | `<span lang dir>`, un `<bdi>` sans `dir` donnant `dir="auto"` |
| `<ruby>`, `<rt>`, `<rp>`, `<rb>` | `<ruby>`, chaque lecture entre les parenthèses `<rp>` écrite à nouveau |
| `<math>` | la formule, gardée comme le permet le profil `rich` de `phil/ujo` |
| `<svg>` | assaini par `phil/svg-sanitizer`, stocké par l'`ExtractedElementStore`, et une `image` de son adresse ; son `<title>` ou son `aria-label` comme texte de remplacement |
| `<br>` | `<br>` |
| `<q>` | son texte entre guillemets |
| `<span class="spoiler">`, `<span data-color>`, `<i data-icon>`, `<span data-hspace>` | le divulgâcheur, la couleur, l'icône et l'espace d'UJO |
| un style `font-weight: bold` ou `700`, `font-style: italic`, `text-decoration: underline` ou `line-through` | `<b>`, `<i>`, `<u>`, `<s>` : ce qu'écrit un collage depuis un traitement de texte |
| `<b style="font-weight:normal">` | son contenu seul : l'enveloppe qu'un traitement de texte met autour d'un collage |
| tout autre élément | son contenu |

Les espaces du texte sont réduites comme un navigateur les réduit, hors de `<pre>`.

## Ce qui est laissé de côté, et les pertes

Les scripts, les styles, les objets, les formulaires et leurs champs et l'en-tête d'une page sont laissés de côté avec
leur contenu. Le titre d'une page, sa langue, `<html lang>`, et ses `<meta name="…" content="…">` sont ses
métadonnées.

Ce que le lecteur ne garde pas est listé dans `Reading::$losses`, dans l'ordre de la source, jusqu'à 1 000 : chaque
`Loss` nomme l'élément, son chemin (`body > div[2] > p[1]`, chaque élément compté parmi ses frères du même nom) et sa
`LossReason` :

| Raison | Quand |
|---|---|
| `UnknownElement` | un élément laissé de côté avec son contenu |
| `UnknownEmbed` | un cadre d'un service que `EmbedServices` ne connaît pas, gardé comme lien |
| `RefusedScheme` | un lien ou une source d'un schéma que `phil/ujo` refuse, son texte gardé |
| `UnknownAnchor` | un lien vers `#…` qu'aucun élément de la page ne nomme, son texte gardé |
| `Depth` | un élément imbriqué plus profondément que ce qui est lu, son texte gardé |
| `SvgWithoutStore` | un dessin en ligne lu sans `ExtractedElementStore` |
| `RefusedSvg` | un dessin que l'assainisseur a refusé en entier, ou que le magasin n'a pas pris |
| `MissingSource` | une vidéo ou un son sans aucune source gardée |
| `DataAttribute` | `data-*` sur un élément en ligne : UJO ne les garde que sur les blocs |
| `Comment` | un commentaire qu'aucun bloc n'a pris comme note |

Un site les montre à l'auteur avant la publication ; `phil/content-bundle` le fait dans `phil:content:publish`.

## Dessins en ligne

Un `<svg>` en ligne est extrait plutôt qu'écrit dans le texte : le lecteur l'écrit en SVG, l'assainit avec
`phil/svg-sanitizer` (aucun script, aucun gestionnaire d'événements, aucune référence hors de l'image, aucun
`foreignObject`) et le donne à un `ExtractedElementStore`, qui stocke le fichier (une médiathèque, un stockage du site)
et retourne son adresse. Le dessin devient une `image` de cette adresse. Sans magasin, ou quand le magasin retourne
`null` ou une adresse qui n'est ni `http`, ni `https`, ni relative, le dessin est laissé de côté et la perte est
enregistrée.

```php
final class MediaStore implements ExtractedElementStore
{
    public function store(string $content, string $mediaType, string $name): ?string
    {
        return $this->library->upload($content, $mediaType, $name)?->url();
    }
}

$reading = new HtmlReader(store: new MediaStore($library))->read($html);
```

## Limites

Les conteneurs s'imbriquent aussi profondément que le permet UJO, et ceux qui sont plus profonds sont laissés de côté,
leurs blocs gardés. Les éléments imbriqués sur plus de 128 niveaux sont lus comme leur texte, pour qu'un document
imbriqué sur des milliers de niveaux se lise en temps linéaire sans épuiser la pile.
