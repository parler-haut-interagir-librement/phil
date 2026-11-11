# HTML

`Reader\Html\HtmlReader` reads HTML into a UJO document: the HTML a block editor renders — that of `phil/ujo`, its
`ujo-*` classes read back into the blocks they came from —, the HTML a paste brings from a word processor or another
site, and the HTML of older texts. `DefaultCompiler` compiles HTML as written, for the purifier; the reader is what
converts it into blocks, when a text is published as UJO or opened in a block editor. [Formats](formats.md) puts each
element beside the syntax of the other formats.

```php
$reading = new HtmlReader()->read('<h2>Rules</h2><p>Roll <strong>two</strong> dice.</p>');

$reading->document->blocks[0];   // a `header` block, level 2, its anchor `rules`
$reading->document->blocks[1];   // a `paragraph` block, `Roll <b>two</b> dice.`
```

The source is parsed by the HTML5 parser of PHP, `Dom\HTMLDocument`, as a browser parses it: tags left open are
closed, misplaced ones moved, entities read. Any text is a document; the reader never throws. The constructor takes,
in order, the `phil/ujo` normalizer the document is checked by, the `EmbedServices` a frame is matched against, the
`ExtractedElementStore` inline drawings are stored by, and the `SvgSanitizer` that purifies them.

## Blocks

| HTML | UJO |
|---|---|
| `<p>`, `<address>`, text between blocks | `paragraph` |
| `<h1>`…`<h6>` | `header`, its anchor the `id`, or generated |
| `<ul>`, `<ol>`, `<menu>`, `<li>` | `list`; `start` and `type` — `a`, `A`, `i`, `I` — kept; another element the list holds is an item too; a list inside an item its nested items, other blocks folded into its content |
| a checkbox, or `data-checked`, in every item | `list`, style `checklist` |
| `<blockquote>` | `quote` |
| `<figure>` holding `<img>` and `<figcaption>` | `image`, the `alt` of the image as its alternative text, the `<figcaption>` as its caption, the layout of the figure as its own |
| `<figure>` holding `<blockquote>` and `<figcaption>` | `quote`, the caption as its attribution |
| `<pre>`, its `<code class="language-php">` | `code`, its language |
| `<hr>` | `delimiter` |
| `<table>`, its `<thead>`, `<tbody>`, `<tfoot>`, `<tr>`, `<th>`, `<td>` | `table`, its rows made as long as the longest, spans counted; a first row of `<th>` only is its headings; `<caption>`, `colspan`, `rowspan`, a `<th>` elsewhere and an alignment — `align`, `text-align`, `ujo-align-…` — make it the rich form; a list in a cell becomes lines, each item after its bullet or number |
| `<iframe>` on a host of a service of `EmbedServices` | `embed`, its `title`, `width` and `height` kept; any other frame is a link to its source |
| `<video>`, `<audio>` | `video`, `audio`: their `src` and `<source>` in order, `<track>` with `kind`, `srclang`, `label` and `default`, `poster`, `controls`, `loop`, `muted`, size and `title`; nothing without a source |
| `<math display="block">` | `math`, its MathML, and the LaTeX of its `application/x-tex` annotation |
| `<p>` holding only `<a download>`, or a link to a file — `.pdf`, `.epub`, `.zip`, `.odt`, `.docx`, `.csv`… | `attaches`: its address, the file's name and extension, the link's text as its title |
| `lang`, `dir` on a block | the `language` tune, when the language differs from the one around it |
| `data-*` on a block | the `data` tune, but for those the reader reads itself or `phil/ujo` writes |
| a comment | the `note` tune of the next block, or of the last one of its container |
| `<dl>`, `<dt>`, `<dd>` | `definitionList` |
| `<details>`, `<summary>` | `toggle`, open when the details are |
| `<center>`, `align="…"`, `style="text-align:…"` | the `alignment` tune |
| `<div>`, `<section>`, `<article>`, `<main>`, `<header>`, `<footer>`, `<nav>`, `<aside>`, and an inline element that holds blocks | their blocks |
| `<img>` in a paragraph | `image`, the paragraph split around it; its `alt` as its alternative text, its `title` as its caption, `width`, `height` and `align` as its layout; inside a link, its alternative text, or its caption without one |

## The HTML of `phil/ujo`

The blocks `phil/ujo` renders carry the class of their type, which the reader reads back:

| Class | UJO |
|---|---|
| `ujo-note`, `ujo-descriptive`, their `ujo-box__title` | `note`, `descriptive` |
| `ujo-warning`, its `ujo-warning__title` and `ujo-warning__message` | `warning` |
| `ujo-alert`, `ujo-alert--danger` | `alert`, its type |
| `ujo-wide`, `ujo-container` and its other classes | `wide`, `container` |
| `ujo-columns` and its `ujo-column`, `ujo-column-flow--3` | `columns`, `columnFlow` |
| `ujo-page-break`, `ujo-column-break`, `ujo-page-style` and its `data-variant` | `pageBreak`, `columnBreak`, `pageStyle` |
| `ujo-spacer` and its `data-lines`, `ujo-page-number` and its `data-mode` | `spacer`, `pageNumber` |
| `ujo-decoration--ornament-12` and its color | `decoration` |
| `ujo-running-footer`, `ujo-watermark`, `ujo-banner` | `runningFooter`, `watermark`, `banner` |
| `ujo-toc` and its `ujo-toc__title` | `toc`, generated again from the headings |
| `ujo-credit` and its `ujo-credit__title` | `credit` |
| `<sup data-footnote="fn-1">` and the `<aside id="footnote-fn-1">` it names | a footnote, numbered again in the order of the references |
| `ujo-align-…`, `ujo-wrap-left`, `ujo-wrap-right` | the `alignment` tune, the wrapping of an image |
| `ujo-embed`, `ujo-video`, `ujo-audio` and their `ujo-print-fallback` | `embed`, `video`, `audio`, the page, poster, title and QR code of their fallback for paper read back |

A stat block is read as its blocks, a class table as its table.

## Inline

| HTML | UJO |
|---|---|
| `<b>`, `<strong>`; `<i>`, `<em>`, `<cite>`, `<dfn>`, `<var>` | `<b>`; `<i>` |
| `<u>`, `<ins>`; `<s>`, `<del>`, `<strike>`; `<mark>`; `<sup>`, `<sub>` | `<u>`; `<s>`; `<mark>`; `<sup>`, `<sub>` |
| `<code>`, `<kbd>`, `<samp>`, `<tt>` | `<code>` |
| `<a href>` to an address of a scheme `phil/ujo` allows — the web, `mailto`, `tel`, `sms`, `geo`, the messengers — or a path of the site | `<a href>`; any other address keeps the text alone |
| `<a href="#…">` | `<a href>` when an element of the page has that `id`; its text alone otherwise |
| `<abbr title>`, `<acronym title>` | `<abbr title>` |
| `lang`, `dir`; `<bdi>`, `<bdo dir>` | `<span lang dir>`, a `<bdi>` without `dir` as `dir="auto"` |
| `<ruby>`, `<rt>`, `<rp>`, `<rb>` | `<ruby>`, each reading between the `<rp>` parentheses written anew |
| `<math>` | the formula, kept as `phil/ujo`'s `rich` profile allows |
| `<svg>` | purified by `phil/svg-sanitizer`, stored by the `ExtractedElementStore`, and an `image` of its address; its `<title>` or `aria-label` as its alternative text |
| `<br>` | `<br>` |
| `<q>` | its text between quotation marks |
| `<span class="spoiler">`, `<span data-color>`, `<i data-icon>`, `<span data-hspace>` | the spoiler, the color, the icon and the space of UJO |
| a style `font-weight: bold` or `700`, `font-style: italic`, `text-decoration: underline` or `line-through` | `<b>`, `<i>`, `<u>`, `<s>`: what a paste from a word processor writes |
| `<b style="font-weight:normal">` | its content alone: the wrapper a word processor puts around a paste |
| any other element | its content |

The white space of text is collapsed as a browser collapses it, out of `<pre>`.

## Left out, and the losses

Scripts, styles, objects, forms and their fields and the head of a page are left out with their content. The title of
a page, its language, `<html lang>`, and its `<meta name="…" content="…">` are its metadata.

What the reader does not keep is listed in `Reading::$losses`, in the order of the source, up to 1 000: each `Loss`
names the element, its path — `body > div[2] > p[1]`, each element counted among its siblings of its name — and its
`LossReason`:

| Reason | When |
|---|---|
| `UnknownElement` | an element left out with its content |
| `UnknownEmbed` | a frame of a service `EmbedServices` does not know, kept as a link |
| `RefusedScheme` | a link or a source of a scheme `phil/ujo` refuses, its text kept |
| `UnknownAnchor` | a link to `#…` that no element of the page names, its text kept |
| `Depth` | an element nested deeper than is read, its text kept |
| `SvgWithoutStore` | an inline drawing read without an `ExtractedElementStore` |
| `RefusedSvg` | a drawing the sanitizer refused whole, or the store did not take |
| `MissingSource` | a video or a sound without any source kept |
| `DataAttribute` | `data-*` on an inline element: UJO keeps them on blocks only |
| `Comment` | a comment no block took as its note |

A site shows them to the writer before publishing; `phil/content-bundle` does in `phil:content:publish`.

## Inline drawings

An inline `<svg>` is extracted rather than written in the text: the reader writes it as SVG, purifies it with
`phil/svg-sanitizer` — no script, no event handler, no reference outside the image, no `foreignObject` — and gives it
to an `ExtractedElementStore`, which stores the file — a media library, a storage of the site — and returns its
address. The drawing becomes an `image` of that address. Without a store, or when the store returns `null` or an
address that is not `http`, `https` or relative, the drawing is left out and the loss recorded.

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

## Limits

Containers nest as deep as UJO allows, and those deeper are left out, their blocks kept. Elements nested more than
128 deep are read as their text, so that a document nested thousands deep is read in linear time without exhausting
the stack.
