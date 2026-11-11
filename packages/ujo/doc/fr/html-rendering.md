<!-- phil-dev source-sha256=ca79973b6d23c94fe5bec6b0c8c5cfed6566503b24009e726c5802aebf69eece -->
# Rendu HTML

`HtmlRenderer::render($document)` produit le rendu d'un document, un bloc de premier niveau par ligne, avec le
`BlockRenderer` enregistré pour chaque type. `HtmlRenderer::default()` enregistre cinq moteurs de rendu pour tous les
types : `TextRenderer`, `CodeRenderer`, `ListRenderer`, `MediaRenderer` et `LayoutRenderer`. Un type sans moteur de
rendu lance `UnknownBlockType`.

## Purification

La sortie est purifiée par construction, grâce à l'assainisseur en ligne propre au moteur de rendu :

- chaque valeur brute est échappée ;
- chaque valeur HTML est assainie de nouveau avec le profil de son champ (`raw` pour un bloc `raw`, `rich` sinon) ;
- chaque élément et chaque attribut qui n'est pas une valeur est écrit par le moteur de rendu lui-même.

Les valeurs qui deviennent des classes, des identifiants, des styles et des adresses sont vérifiées par `Normalizer`,
pas par le moteur de rendu : produisez le rendu d'un document normalisé. `Ujo::toHtml()` normalise d'abord ;
donnez-lui le document ou son JSON.

## Le HTML de chaque type

Chaque élément racine a la classe `ujo-` suivie de son type en kebab case : `ujo-paragraph`, `ujo-link-tool`,
`ujo-page-break`.

| Type | HTML |
|---|---|
| `paragraph` | `<p>` |
| `header` | `<h1>` à `<h6>`, son `id` étant le réglage d'ancre ou l'identifiant du bloc |
| `list` | `<ul>` ou `<ol>` avec `start` et `type`, classe `ujo-list--{style}`, les listes imbriquées dans leur élément ; un élément de liste de contrôle a `data-checked="true"` ou `"false"` |
| `quote` | `<figure><blockquote>…</blockquote><figcaption>…</figcaption></figure>`, `ujo-align-center` quand elle est centrée |
| `warning` | `<aside role="note">` avec `ujo-warning__title` et `ujo-warning__message` |
| `alert` | `<div role="alert">`, classes `ujo-alert--{type}` et `ujo-align-{align}` |
| `code` | `<pre><code class="language-{language}">`, la classe omise sans langue ; coloré par un surligneur quand il y en a un |
| `raw` | `<div>` qui contient le HTML |
| `delimiter` | `<hr>`, classe `ujo-delimiter--{style}` |
| `table` | `<table>`, un `<thead>` avec `withHeadings`, `ujo-table--stretched` ; dans la forme riche, `<caption class="ujo-table__caption">`, `colspan` et `rowspan`, une cellule d'en-tête en `<th>` dont le `scope` est `col` ou `colgroup` dans l'en-tête et `row` ou `rowgroup` dans le corps, et `ujo-align-{align}` tiré de la cellule ou, sinon, de sa colonne |
| `image` | `<figure>` avec un `<img>` différé dont le `alt` est celui du bloc, ou à défaut le texte de la légende, `<figcaption>` et le crédit Unsplash ; `ujo-image--border`, `--background`, `--stretched` |
| `gallery` | `<div>` de `<figure>`, classe `ujo-gallery--{style}` |
| `embed` | `<figure>` avec un `<iframe>` différé, en bac à sable, qui n'envoie que son origine comme référent, puis sa solution de repli pour le papier |
| `video`, `audio` | `<figure>` avec `<video>` ou `<audio>`, `preload="metadata"`, ses `controls`, `loop`, `muted`, `poster`, sa taille et son `title`, un `<source>` par source et un `<track>` par piste, puis sa solution de repli pour le papier et son `<figcaption>` |
| `linkTool` | `<a rel="noopener nofollow">` avec image, titre, description et site |
| `attaches` | `<a download>` avec `data-size` et `data-extension` |
| `personality` | `<div>` avec photo, nom (en lien quand il y a un lien) et description |
| `button` | `<a>` |
| `math` | `<div>` qui contient son MathML, assaini de nouveau avec le profil `mathml`, ou sinon son LaTeX, qu'un script de l'apparence dessine ; son MathML approuvé avec `withStoredMarkup()` |
| `mermaid` | `<figure><pre class="mermaid">` ; son SVG approuvé avec `withStoredMarkup()` |
| `chart` | `<div data-chart="{json}">`, qu'un script de l'apparence dessine ; une `<figure>` de son SVG approuvé avec `withStoredMarkup()` |
| `qrCode` | `<div data-qr-code>` qui contient le texte ; une `<figure>` de son SVG approuvé avec `withStoredMarkup()` |
| `toggle` | `<details>`, `open` quand il est ouvert, avec `<summary>` |
| `columns` | `<div class="ujo-columns--{n}">` de `<div class="ujo-column">` |
| `note`, `descriptive` | `<aside>` avec `ujo-box__title` |
| `wide`, `columnFlow`, `container` | `<div>` ; `ujo-column-flow--{count}` ; les classes du conteneur |
| `pageBreak`, `columnBreak`, `pageStyle`, `spacer`, `decoration` | `<div aria-hidden="true">` vide, avec `data-variant`, `data-lines`, `ujo-decoration--{kind}-{variant}` et `--ujo-decoration-color` |
| `pageNumber` | `<span data-mode>`, la valeur quand elle est fixe |
| `runningFooter`, `banner`, `watermark` | `<div>` |
| `footnote` | `<aside id="footnote-{id}" role="note">` |
| `toc` | `<nav>` avec une `<ol>` des titres jusqu'à sa profondeur, `ujo-toc__level-{n}`, en lien vers l'`id` de chaque titre ; dix par document au plus |
| `index` | `<section class="ujo-columns-{n}">` de `<ul>` imbriquées |
| `abbreviations` | `<section>` avec `ujo-abbreviations__title` et une `<dl>` : chaque abréviation du document dans `<dt><abbr title>`, son sens dans `<dd>` ; dix par document au plus |
| `definitionList` | `<dl>` |
| `credit` | `<p>` avec `ujo-credit__title` |
| `statblock` | `<section>`, classes `ujo-statblock--{kind}`, `ujo-frame`, `ujo-wide`, `data-record` ; le nom dans `<h3>`, les sections avec `<h4>`, une `<dl>` des propriétés et une `<table>` des capacités |

## Réglages

| Réglage | Sur l'élément racine |
|---|---|
| `anchor` | `id`, qui l'emporte sur celui du moteur de rendu |
| `alignment` | `ujo-align-{alignment}` |
| `textVariant` | `ujo-text-{variant}` |
| `notice` | `ujo-notice ujo-notice-{style}`, `data-notice-caption` |
| `indent` | `ujo-indent-{level}`, à partir de 1 |
| `variant` | `ujo-variant-{name}`, `ujo-frame`, `ujo-decoration` |
| `mask` | `ujo-mask-{shape}-{variant}`, et les propriétés personnalisées `--ujo-mask-offset-x`, `--ujo-mask-offset-y`, `--ujo-mask-rotation` |
| `layout` | `position`, `top`, `right`, `bottom`, `left`, `width`, `height`, `z-index` dans `style`, et `ujo-wrap-{side}` |
| `toc` | `data-toc="exclude"` quand le titre est omis |
| `language` | `lang` et `dir` |
| `data` | `data-{name}` pour chaque valeur, une fois que `withDataAttributes()` les demande ; jamais un nom qu'écrit le moteur de rendu ou un autre réglage |
| `note` | rien : la note d'un rédacteur n'est jamais publiée |

## La solution de repli pour le papier

Un cadre, une vidéo et un son ne peuvent pas jouer sur papier. Chacun est suivi d'un
`<a class="ujo-print-fallback">`, en lien vers la page du cadre ou la première source du lecteur, avec son affiche
`ujo-print-fallback__poster`, son titre `ujo-print-fallback__title` et son adresse `ujo-print-fallback__url`. Quand le
`qrCode` du bloc est vrai, il est aussi suivi d'un `<div class="ujo-print-fallback ujo-qr-code" data-qr-code>`, que
dessine un moteur de rendu de codes QR. Un style d'écran masque `.ujo-print-fallback` et un style d'impression le
montre à la place du lecteur.

## Remplacer ou ajouter un moteur de rendu

```php
final readonly class CalloutRenderer implements BlockRenderer
{
    public function render(Block $block, Rendering $rendering): string
    {
        return $rendering->element('aside', $block, $rendering->inline($block->data['text'] ?? null), ['role' => 'note'], ['ujo-callout--'.Rendering::text($block->data['tone'] ?? 'calm')]);
    }
}

$renderer = HtmlRenderer::default()->with('callout', new CalloutRenderer());
```

`Rendering` donne à un moteur de rendu le document (`$rendering->document`), les blocs imbriqués (`blocks()`),
l'assainisseur (`inline()`, `plain()`), l'échappement (`text()`), l'élément racine avec les réglages (`element()`), les
éléments simples (`tag()`) et les valeurs calculées une fois par rendu (`remember($key, $compute)`) : un moteur de rendu
qui lit le document entier le lit une seule fois, quel que soit le nombre de ses blocs que contient le document. Les
titres de `toc` et les abréviations de `abbreviations` sont lus de cette façon. Un autre assainisseur se donne à
`HtmlRenderer::default($sanitizer)`.

## Surligner le code

`withCodeHighlighter()` donne un surligneur aux blocs de code : une fermeture qui reçoit le code et sa langue et
retourne du HTML, ou `null` quand elle ne connaît pas la langue. Elle n'est pas appelée pour un bloc sans langue.
`Highlighter::highlight()` de `phil/highlight` a cette forme :

```php
$renderer = HtmlRenderer::default()->withCodeHighlighter(Highlighter::default()->highlight(...));
```

Le moteur de rendu ne garde le HTML du surligneur que s'il ne contient rien d'autre que le texte du code, échappé, dans
des éléments `<span class="…">` (lettres minuscules, chiffres, espaces, `_` et `-` dans la classe) qui s'ouvrent et se
ferment dans l'ordre (`CodeRenderer::holds()`). Tout le reste se rabat sur le code échappé : le moteur de rendu reste
ainsi purifié par construction, quoi que retourne le surligneur. Un bloc que le surligneur laisse garde sa classe
`language-…`, pour un surligneur dans le navigateur.

## Dessins stockés

`withStoredMarkup(Closure $trusts, array $types = StoredMarkup::TYPES)` dessine les blocs `math`, `mermaid`, `chart`
et `qrCode` à partir du balisage qu'ils gardent sous `rendered`. `$trusts` reçoit le bloc, le balisage et sa clé, et dit
si le serveur les a écrits pour la source actuelle du bloc ; `Signer::trusts(...)` de `phil/svg-blocks` a cette forme :

```php
$renderer = HtmlRenderer::default()->withStoredMarkup($signer->trusts(...));
```

Une formule approuvée s'écrit comme un `<div class="ujo-math">` autour de son MathML ; un dessin approuvé comme une
`<figure>` autour de son SVG, avec la légende du bloc. Le balisage est écrit tel quel, sans nouvel assainissement : on
lui fait confiance parce que sa clé prouve qui l'a écrit, et `$trusts` doit donc refuser toute clé qu'il n'a pas émise.
Un bloc sans dessin, avec un dessin mal formé ou avec une clé non approuvée se rabat sur le moteur de rendu que le type
avait avant (`StoredMarkupRenderer`).

## Filtrer le HTML produit

`withFilter()` ajoute un `HtmlFilter`, une dernière passe sur le HTML de chaque document, exécutée après les filtres
que le moteur de rendu a déjà. Un filtre reçoit du HTML purifié et doit retourner du HTML aussi sûr : il peut réécrire
du texte (guillemets typographiques, espaces insécables, césures conditionnelles), jamais ajouter de balisage ni
d'attributs.

```php
final readonly class Typography implements HtmlFilter
{
    public function filter(string $html): string
    {
        return str_replace('...', '…', $html);
    }
}

$renderer = HtmlRenderer::default()->withFilter(new Typography());
```
