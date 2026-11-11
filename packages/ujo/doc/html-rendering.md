# HTML rendering

`HtmlRenderer::render($document)` renders a document, one top-level block per line, with the `BlockRenderer`
registered for each type. `HtmlRenderer::default()` registers five renderers for every type: `TextRenderer`,
`CodeRenderer`, `ListRenderer`, `MediaRenderer` and `LayoutRenderer`. A type without renderer throws `UnknownBlockType`.

## Purification

The output is purified by construction, through the renderer's own inline sanitizer:

- every plain value is escaped;
- every HTML value is sanitized again with the profile of its field (`raw` for a `raw` block, `rich` otherwise);
- every element and attribute that is not a value is written by the renderer itself.

The values that become classes, identifiers, styles and addresses are checked by `Normalizer`, not by the renderer:
render a normalized document. `Ujo::toHtml()` normalizes first; give it the document or its JSON.

## The HTML of each type

Every root element has the class `ujo-` followed by its type in kebab case: `ujo-paragraph`, `ujo-link-tool`,
`ujo-page-break`.

| Type | HTML |
|---|---|
| `paragraph` | `<p>` |
| `header` | `<h1>`–`<h6>`, its `id` the anchor tune or the block's identifier |
| `list` | `<ul>` or `<ol>` with `start` and `type`, class `ujo-list--{style}`, nested lists inside their item; a checklist item has `data-checked="true"` or `"false"` |
| `quote` | `<figure><blockquote>…</blockquote><figcaption>…</figcaption></figure>`, `ujo-align-center` when centered |
| `warning` | `<aside role="note">` with `ujo-warning__title` and `ujo-warning__message` |
| `alert` | `<div role="alert">`, classes `ujo-alert--{type}` and `ujo-align-{align}` |
| `code` | `<pre><code class="language-{language}">`, the class left out without a language; coloured by a highlighter when one is given |
| `raw` | `<div>` holding the HTML |
| `delimiter` | `<hr>`, class `ujo-delimiter--{style}` |
| `table` | `<table>`, a `<thead>` when `withHeadings`, `ujo-table--stretched`; in the rich form, `<caption class="ujo-table__caption">`, `colspan` and `rowspan`, a header cell a `<th>` whose `scope` is `col` or `colgroup` in the head and `row` or `rowgroup` in the body, and `ujo-align-{align}` from the cell or else its column |
| `image` | `<figure>` with a lazy `<img>` whose `alt` is the block's `alt`, or the caption's text without one, `<figcaption>` and the Unsplash credit; `ujo-image--border`, `--background`, `--stretched` |
| `gallery` | `<div>` of `<figure>`, class `ujo-gallery--{style}` |
| `embed` | `<figure>` with a lazy, sandboxed `<iframe>` sending only its origin as referrer, then its fallback for paper |
| `video`, `audio` | `<figure>` with `<video>` or `<audio>`, `preload="metadata"`, its `controls`, `loop`, `muted`, `poster`, size and `title`, a `<source>` per source and a `<track>` per track, then its fallback for paper and its `<figcaption>` |
| `linkTool` | `<a rel="noopener nofollow">` with image, title, description and site |
| `attaches` | `<a download>` with `data-size` and `data-extension` |
| `personality` | `<div>` with photo, name — linked when there is a link — and description |
| `button` | `<a>` |
| `math` | `<div>` holding its MathML, sanitized again with the `mathml` profile, or else its LaTeX, for a script of the look to draw; its trusted MathML with `withStoredMarkup()` |
| `mermaid` | `<figure><pre class="mermaid">`; its trusted SVG with `withStoredMarkup()` |
| `chart` | `<div data-chart="{json}">`, for a script of the look to draw; `<figure>` of its trusted SVG with `withStoredMarkup()` |
| `qrCode` | `<div data-qr-code>` holding the text; `<figure>` of its trusted SVG with `withStoredMarkup()` |
| `toggle` | `<details>`, `open` when open, with `<summary>` |
| `columns` | `<div class="ujo-columns--{n}">` of `<div class="ujo-column">` |
| `note`, `descriptive` | `<aside>` with `ujo-box__title` |
| `wide`, `columnFlow`, `container` | `<div>`; `ujo-column-flow--{count}`; the container's classes |
| `pageBreak`, `columnBreak`, `pageStyle`, `spacer`, `decoration` | empty `<div aria-hidden="true">`, with `data-variant`, `data-lines`, `ujo-decoration--{kind}-{variant}` and `--ujo-decoration-color` |
| `pageNumber` | `<span data-mode>`, the value when fixed |
| `runningFooter`, `banner`, `watermark` | `<div>` |
| `footnote` | `<aside id="footnote-{id}" role="note">` |
| `toc` | `<nav>` with an `<ol>` of the headings up to its depth, `ujo-toc__level-{n}`, linking to each heading's `id`; ten per document at most |
| `index` | `<section class="ujo-columns-{n}">` of nested `<ul>` |
| `abbreviations` | `<section>` with `ujo-abbreviations__title` and a `<dl>`: each abbreviation of the document in `<dt><abbr title>`, its meaning in `<dd>`; ten per document at most |
| `definitionList` | `<dl>` |
| `credit` | `<p>` with `ujo-credit__title` |
| `statblock` | `<section>`, classes `ujo-statblock--{kind}`, `ujo-frame`, `ujo-wide`, `data-record`; the name in `<h3>`, sections with `<h4>`, a `<dl>` of properties and a `<table>` of abilities |

## Tunes

| Tune | On the root element |
|---|---|
| `anchor` | `id`, which wins over the renderer's |
| `alignment` | `ujo-align-{alignment}` |
| `textVariant` | `ujo-text-{variant}` |
| `notice` | `ujo-notice ujo-notice-{style}`, `data-notice-caption` |
| `indent` | `ujo-indent-{level}`, from 1 |
| `variant` | `ujo-variant-{name}`, `ujo-frame`, `ujo-decoration` |
| `mask` | `ujo-mask-{shape}-{variant}`, and the custom properties `--ujo-mask-offset-x`, `--ujo-mask-offset-y`, `--ujo-mask-rotation` |
| `layout` | `position`, `top`, `right`, `bottom`, `left`, `width`, `height`, `z-index` in `style`, and `ujo-wrap-{side}` |
| `toc` | `data-toc="exclude"` when the heading is left out |
| `language` | `lang` and `dir` |
| `data` | `data-{name}` for each value, once `withDataAttributes()` asks for them — never a name the renderer or another tune writes |
| `note` | nothing: a writer's note is never published |

## The fallback for paper

A frame, a video and a sound cannot play on paper. Each is followed by `<a class="ujo-print-fallback">`, linking to the
page of the frame or the player's first source, with its poster `ujo-print-fallback__poster`, its title
`ujo-print-fallback__title` and its address `ujo-print-fallback__url`, and, when the block's `qrCode` is true, by
`<div class="ujo-print-fallback ujo-qr-code" data-qr-code>`, which a QR code renderer draws. A screen style hides
`.ujo-print-fallback` and a print style shows it in place of the player.

## Replacing or adding a renderer

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

`Rendering` gives a renderer the document (`$rendering->document`), the nested blocks (`blocks()`), the sanitizer
(`inline()`, `plain()`), escaping (`text()`), the root element with the tunes (`element()`), plain elements
(`tag()`) and values computed once per rendering (`remember($key, $compute)`): a renderer that reads the whole
document reads it once, however many of its blocks the document holds — the headings of `toc` and the abbreviations of
`abbreviations` are read that way. A different sanitizer is given to `HtmlRenderer::default($sanitizer)`.

## Highlighting code

`withCodeHighlighter()` gives the code blocks a highlighter: a closure from the code and its language to HTML, or to
`null` when it does not know the language. It is not called for a block without a language. `phil/highlight`'s
`Highlighter::highlight()` has that shape:

```php
$renderer = HtmlRenderer::default()->withCodeHighlighter(Highlighter::default()->highlight(...));
```

The renderer keeps the highlighter's HTML only when it holds nothing but the code's text, escaped, inside
`<span class="…">` elements — lower-case letters, digits, spaces, `_` and `-` in the class — that open and close in
order (`CodeRenderer::holds()`). Anything else falls back to the escaped code, so the renderer stays purified by
construction whatever the highlighter returns. A block the highlighter leaves keeps its `language-…` class, for a
highlighter in the browser.

## Stored drawings

`withStoredMarkup(Closure $trusts, array $types = StoredMarkup::TYPES)` draws the blocks of `math`, `mermaid`, `chart`
and `qrCode` from the markup they keep under `rendered`. `$trusts` receives the block, the markup and its key, and
says whether the server wrote them for the block's current source — `phil/svg-blocks`' `Signer::trusts(...)` has
that shape:

```php
$renderer = HtmlRenderer::default()->withStoredMarkup($signer->trusts(...));
```

A trusted formula is written as `<div class="ujo-math">` around its MathML; a trusted drawing as a `<figure>` around
its SVG, with the block's caption. The markup is written as it is, not sanitized again: it is trusted because its key
proves who wrote it, so `$trusts` must refuse any key it did not issue. A block without a drawing, with a misshaped
one or with an untrusted key falls back to the renderer the type had before (`StoredMarkupRenderer`).

## Filtering the rendered HTML

`withFilter()` adds an `HtmlFilter`, a last pass over the HTML of each document, run after the filters the renderer
already has. A filter receives purified HTML and must return HTML as safe: it may rewrite text — typographic quotes,
no-break spaces, soft hyphens —, never add markup or attributes.

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
